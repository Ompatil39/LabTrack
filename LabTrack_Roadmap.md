# LabTrack: Roadmap from Entry-Level to Advanced

Work through the phases **in order**. Each phase builds on the one before it. Don't start a phase until the previous one's "Done when" checks pass.

| Phase | Focus | Est. time |
|---|---|---|
| 0 | Repo hygiene and dev environment | 2-3 days |
| 1 | Security fixes | 1 week |
| 2 | **Fix the data model (link components to PCs)** | 1-2 weeks |
| 3 | Architecture refactor | 2-3 weeks |
| 4 | Testing and CI | 1 week |
| 5 | Core feature upgrades (tickets, audit, lifecycle) | 2-3 weeks |
| 6 | Monitoring agent and telemetry | 2-3 weeks |
| 7 | Network discovery and monitoring | 2-3 weeks |
| 8 | Printers, software compliance, remote actions | 2 weeks |
| 9 | Predictive maintenance and anomaly detection | 2-3 weeks |
| 10 | Observability, deployment, portfolio polish | 1-2 weeks |

---

## The Biggest Flaw: Components Are Not Linked to PCs

### What is wrong today

In `databse_tables.sql`, the tables `monitors`, `keyboards`, `mice`, `cpus` each only reference `devices(device_id)`. Nothing ties a monitor, keyboard, mouse, or CPU to a specific PC. In `addDevice.php`, a "PC" entry creates five separate rows in `devices` (PC, Monitor, Keyboard, Mouse, CPU) with five unrelated IDs.

Consequences:

- You can't answer "which monitor belongs to PC-2025-0007?"
- A grievance on a keyboard can't be traced to its workstation or seat.
- Swapping a faulty mouse loses the history of where the old one went.
- You can't compute per-workstation health or total cost.
- Network monitoring (Phase 7) can't attribute an offline PC to its parts.

### The fix: workstations, assets, and assignment history

Model three ideas:

1. **Workstation** = a physical seat in a lab (Lab A, Seat 12). It's stable even when hardware changes.
2. **Asset** = any physical item with its own identity (PC, monitor, mouse, printer...).
3. **Assignment** = which asset sits at which workstation, and when (history is kept).

```sql
-- A physical seat/position in a lab
CREATE TABLE workstations (
  workstation_id  INT AUTO_INCREMENT PRIMARY KEY,
  lab_id          VARCHAR(20) NOT NULL,
  seat_code       VARCHAR(20) NOT NULL,            -- e.g. 'A-12'
  hostname        VARCHAR(100),
  status          ENUM('Active','Inactive','Under Repair') NOT NULL DEFAULT 'Active',
  UNIQUE (lab_id, seat_code),
  FOREIGN KEY (lab_id) REFERENCES labs(lab_id)
);

-- Every physical thing is an asset (one table instead of seven)
CREATE TABLE assets (
  asset_id        INT AUTO_INCREMENT PRIMARY KEY,
  asset_tag       VARCHAR(30) NOT NULL UNIQUE,     -- printed on QR, e.g. 'MON-2026-0004'
  asset_type      ENUM('PC','Monitor','Keyboard','Mouse','CPU','Printer','Switch','AccessPoint','Other') NOT NULL,
  lab_id          VARCHAR(20) NOT NULL,
  brand_model     VARCHAR(100),
  serial_number   VARCHAR(100) UNIQUE,
  status          ENUM('Active','Inactive','Under Repair','Faulty','Retired') NOT NULL DEFAULT 'Active',
  purchase_date   DATE,
  warranty_until  DATE,
  purchase_cost   DECIMAL(10,2),
  specs           JSON,                            -- type-specific: ram, resolution, toner...
  qr_payload      VARCHAR(255),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lab_id) REFERENCES labs(lab_id),
  INDEX (asset_type), INDEX (status)
);

-- Which asset is at which workstation, with history
CREATE TABLE asset_assignments (
  assignment_id   INT AUTO_INCREMENT PRIMARY KEY,
  asset_id        INT NOT NULL,
  workstation_id  INT NOT NULL,
  parent_asset_id INT NULL,                        -- e.g. monitor -> its PC
  installed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  removed_at      DATETIME NULL,                   -- NULL = currently installed
  removal_reason  VARCHAR(255),
  FOREIGN KEY (asset_id) REFERENCES assets(asset_id),
  FOREIGN KEY (workstation_id) REFERENCES workstations(workstation_id),
  FOREIGN KEY (parent_asset_id) REFERENCES assets(asset_id)
);

-- Enforce: an asset can only be installed in one place at a time
-- (enforce in application logic plus a unique index on a generated "active" column)
```

Useful queries this unlocks:

```sql
-- Everything currently installed at a seat
SELECT a.asset_tag, a.asset_type, a.status
FROM asset_assignments aa
JOIN assets a ON a.asset_id = aa.asset_id
WHERE aa.workstation_id = ? AND aa.removed_at IS NULL;

-- Full history of one mouse
SELECT w.seat_code, aa.installed_at, aa.removed_at, aa.removal_reason
FROM asset_assignments aa JOIN workstations w USING (workstation_id)
WHERE aa.asset_id = ? ORDER BY aa.installed_at;
```

### Migration steps (do these in Phase 2)

1. Create the new tables above (via a migration file, not by hand).
2. Write a one-off script that copies `devices` plus the type tables into `assets`, putting type-specific columns into `specs` JSON.
3. For each existing PC, **manually or semi-automatically group** its components into a workstation (group by creation timestamp: the five rows created in one `addDevice` call share nearly identical `created_at`).
4. Create `workstations` and `asset_assignments` rows from those groups.
5. Update the UI: "Add Workstation" creates one workstation and assigns the parts in one transaction.
6. Keep the old tables read-only for one release, then drop them.

**Done when:** opening a workstation page shows its PC, monitor, keyboard, mouse, and CPU together, and a part swap preserves history.

---

## Phase 0: Repo Hygiene and Dev Environment (2-3 days)

- [ ] Add a real `LICENSE` file (the README badge links to a file that doesn't exist).
- [ ] Remove `vendor/` from git and add it to `.gitignore`. Keep `composer.json` and `composer.lock`.
- [ ] Move `DB_schema.pdf`, `db.png`, and the `.mp4` files out of the repo root (use `docs/` or release assets).
- [ ] Rename `databse_tables.sql` (typo) and plan to replace it with migrations.
- [ ] Add `Dockerfile` and `docker-compose.yml` (PHP-FPM, Nginx, MySQL, optionally Redis and phpMyAdmin).
- [ ] Add `.env.example` and load config from environment (e.g. `vlucas/phpdotenv`).
- [ ] Start a Git branching flow: `main`, `dev`, and feature branches. Use small, meaningful commits and conventional commit messages.

**Done when:** `docker compose up` runs the app on a fresh machine with no manual setup.

---

## Phase 1: Security Fixes (1 week)

Fix the known issues first; later work shouldn't be built on an insecure base.

- [ ] **SQL injection:** convert every raw query using user input to prepared statements. Known spots: `filterDevices.php` (`lab_id` is unescaped), `grievance.php` (delete uses `$grievance_id` and `$reason` directly), plus `addDevice.php`'s ID generator. Grep for `->query(` and `mysqli_query(`.
- [ ] **Authentication on every endpoint:** `filterDevices.php` and `get_devices.php` currently have no login check.
- [ ] **Fix the auth check pattern:** replace `isset($_SESSION['logged_in']) !== true` with a single `requireLogin()` helper.
- [ ] **Role-based access control:** a central `requireRole('Admin')` helper. Incharges must not reach admin pages, and should only see their own labs.
- [ ] **CSRF tokens** on all state-changing forms (the README claims this, but it doesn't exist).
- [ ] **Sessions:** `session_regenerate_id(true)` on login, secure/HttpOnly/SameSite cookie flags, idle timeout.
- [ ] **Login hardening:** rate limiting and lockout after N failed attempts; remove the hardcoded `admin123` hash generation from `login.php`; force a password change on first login.
- [ ] **Stronger CAPTCHA:** use `random_int()` instead of `rand()`, or switch to a proper CAPTCHA service.
- [ ] **Output encoding:** `htmlspecialchars` everywhere user data is echoed (for example `data-name` attributes in `filterDevices.php`).
- [ ] **Error handling:** stop using `die()` with raw DB errors; log errors server-side and show generic messages.
- [ ] **Database user:** stop using root with an empty password; create a least-privilege user.
- [ ] **Fix schema/code mismatches:** `devices.status` uses `Under Repair`, but dashboard code queries `In Repair`/`Faulty`. Pick one set of statuses and use it everywhere (ideally a constants class).

**Done when:** a basic scan (OWASP ZAP baseline) shows no high-severity findings, and an Incharge gets a 403 on admin routes.

---

## Phase 2: Fix the Data Model (1-2 weeks)

Do the **workstation/asset/assignment redesign** described above. Also in this phase:

- [ ] Make `labs.lab_incharge` a foreign key to `users.user_id` instead of a name string.
- [ ] Add a `lab_incharges` table (many-to-many) if one person can run several labs or one lab has several incharges.
- [ ] Replace the "select last ID + 1" device ID generator with a DB-backed sequence table (or auto-increment plus a formatted tag) to remove the race condition.
- [ ] Link grievances to `asset_id` and `workstation_id`, not just a free-form device name.
- [ ] Add indexes on common filters (`lab_id`, `status`, `asset_type`, `created_at`).
- [ ] Use a migration tool (Phinx, Doctrine Migrations, or Laravel migrations) with up/down scripts and seeders for demo data.

**Done when:** schema changes only happen through versioned migrations, and a workstation page shows all of its parts.

---

## Phase 3: Architecture Refactor (2-3 weeks)

Move from page-per-file scripts to a structured application.

**Option A (recommended): Laravel.** Fastest path to a professional result: Eloquent, migrations, auth, policies, queues, and testing built in.
**Option B: Slim or plain PHP with your own layers.** More educational, more work.
**Option C: REST API (Laravel/Slim) plus React or Next.js frontend.** Best if you want a modern SPA and a stronger portfolio.

Whichever you choose:

- [x] Single entry point (`public/index.php`) with a router; no more `html pages/*.php` scripts.
- [x] Layers: Controller -> Service -> Repository -> Model.
- [x] Move HTML into templates (Blade or Twig) and move inline CSS/JS (e.g. the 2,118-line `submitGrievance.php`) into separate asset files.
- [x] Form validation in dedicated request classes.
- [x] Consistent JSON API responses for AJAX endpoints; document with **OpenAPI/Swagger**.
- [x] Replace the unmaintained `fpdf.php` copy in `lib/` with the Composer-managed library you already list (TCPDF).
- [x] Port features one module at a time (labs -> workstations/assets -> grievances -> dashboard) and keep the old app working until each is replaced.

**Done when:** every old page has an equivalent route, and the old `html pages/` folder is deleted.

---

## Phase 4: Testing and CI (1 week)

- [x] Unit tests for services (ID/tag generation, assignment rules, status transitions).
- [x] Feature tests for each route: auth, roles, CSRF, validation.
- [x] Test the swap flow: removing a part ends its assignment and keeps history.
- [x] Static analysis: PHPStan (level 6+) and a code style tool (PHP-CS-Fixer or Pint).
- [x] GitHub Actions workflow: install, lint, static analysis, tests with a MySQL service container, on every push and PR.
- [x] Coverage report with a README badge. Target 70%+ on core logic.

**Done when:** a failing test or lint error blocks merging to `main`.

---

## Phase 5: Core Feature Upgrades (2-3 weeks)

### 5.1 Audit log
- [ ] `audit_logs` table: who, what, entity, old value, new value, IP, timestamp.
- [ ] Record every create, update, delete, and login event.
- [ ] Admin screen to search and filter the log.

### 5.2 Tickets (upgrade from grievances)
- [ ] Tables: `tickets`, `ticket_comments`, `ticket_status_history`, `sla_policies`.
- [ ] Link each ticket to an asset and a workstation.
- [ ] Priorities and SLA timers (e.g. High = respond in 4h, resolve in 24h).
- [ ] Auto-assign to the lab's incharge; escalate to Admin when the SLA is about to breach.
- [ ] Public ticket status page by tracking code (students don't need accounts).
- [ ] Replace inline mail code with a **queued** notification system (database or Redis queue plus a worker) with retries.

### 5.3 Maintenance and lifecycle
- [ ] `maintenance_records`: asset, type (repair, preventive), cost, vendor, start/end, downtime hours.
- [ ] Scheduled preventive maintenance with reminders (this also gives the dashboard stats your `todo.txt` planned).
- [ ] Warranty-expiry alerts and a depreciation report.
- [ ] Retire/dispose workflow so retired assets leave active inventory but stay in history.

### 5.4 Better QR flow
- [ ] QR points to a short URL (`/a/MON-2026-0004`) that works as a **mobile-friendly page**.
- [ ] From that page: view details, report an issue (pre-filled ticket), and for staff, log maintenance.
- [ ] Make it a PWA so scanning works smoothly on phones.

### 5.5 Dashboard
- [ ] Replace sample/hardcoded chart data with real queries, cached for performance.
- [ ] Add the stats from `todo.txt`: resolution time, tickets per lab, maintenance cost, downtime by lab.

**Done when:** a student scans a QR code, files a ticket, the incharge is notified, and the full history appears on the asset page.

---

## Phase 6: Monitoring Agent and Telemetry (2-3 weeks)

A small program on each lab PC that reports its health to the server.

- [ ] Choose the language: **Python** (fastest to build) or **Go** (single static binary, easiest to deploy). Use `psutil` in Python.
- [ ] Agent collects: hostname, OS and version, CPU/RAM/disk usage, uptime, IP and MAC addresses, logged-in user, installed software list (weekly), S.M.A.R.T. disk health and CPU temperature where available.
- [ ] **Enrollment:** admin generates a one-time token per workstation; the agent exchanges it for a long-lived API key. The agent links itself to a workstation by MAC or hostname.
- [ ] **Secure transport:** HTTPS only, per-agent API keys, signed or hashed payloads, server-side rate limits.
- [ ] Server endpoints: `POST /api/agent/heartbeat` and `POST /api/agent/inventory`.
- [ ] Storage: `telemetry_samples` table (partition by month) or a time-series DB (TimescaleDB/InfluxDB) if volume grows.
- [ ] Retention policy: keep raw samples 30 days, hourly rollups 1 year.
- [ ] Windows service / systemd unit so the agent starts on boot and auto-updates.
- [ ] **Compare reported hardware with the registered specs.** Flag mismatches (RAM was upgraded or stolen, disk swapped).

**Done when:** a workstation page shows live status, last-seen time, and a hardware drift warning if the PC doesn't match its record.

---

## Phase 7: Network Discovery and Monitoring (2-3 weeks)

> **Permission first:** only scan networks you own or have written approval to scan (get sign-off from your institute's IT department). Rate-limit scans and log every scan run.

### 7.1 Discovery
- [ ] Add `networks` (subnet, VLAN, lab) and `network_hosts` (IP, MAC, vendor, first seen, last seen, asset link).
- [ ] Worker job: ARP/ICMP sweep of configured subnets (use `nmap -sn` or Python `scapy`/`python-nmap`) on a schedule.
- [ ] Match MAC addresses to registered assets (`specs.mac` or the agent's data).
- [ ] **Rogue device detection:** an unknown MAC raises an alert.
- [ ] Detect IP mismatches and duplicate IPs.

### 7.2 Uptime monitoring
- [ ] Worker pings or TCP-checks each asset every 1-5 minutes.
- [ ] `uptime_checks` table (asset, timestamp, up/down, latency).
- [ ] Debounce: alert only after N consecutive failures to avoid false alarms.
- [ ] Availability % per asset, workstation, and lab.
- [ ] Auto-create a ticket when a PC stays offline during lab hours.

### 7.3 Port and service scan (optional, careful)
- [ ] Weekly scan of common ports (RDP 3389, SSH 22, SMB 445, Telnet 23).
- [ ] Flag risky open services in a security report.

### 7.4 Topology and quality
- [ ] Add switches and access points as assets; record which port each workstation connects to.
- [ ] Topology graph UI (Cytoscape.js or D3).
- [ ] Latency and packet-loss trends per lab to spot a failing switch.

**Done when:** you can unplug a PC and watch it go red on the dashboard, trigger an alert, and open an auto-created ticket linked to the right seat.

---

## Phase 8: Printers, Software Compliance, Remote Actions (2 weeks)

### 8.1 Printer monitoring
- [ ] Poll printers over **SNMP** (toner level, paper tray, error state, page count).
- [ ] Auto-ticket on low toner or paper jam; track consumable usage and cost.

### 8.2 Software and compliance
- [ ] Use the agent's software inventory to flag: missing antivirus, outdated OS, forbidden software, missing required software per lab.
- [ ] Per-lab compliance score on the dashboard.
- [ ] Check installed versions against a vulnerability feed (NVD/OSV) and report CVE exposure.

### 8.3 Remote actions
- [ ] **Wake-on-LAN** and scheduled shutdown for whole labs (saves electricity).
- [ ] Remote commands via the agent (restart, run a pre-approved script), with strict allow-listing, admin-only access, and audit logging.

### 8.4 Usage analytics
- [ ] Log sessions per workstation; show under/over-used machines and peak hours for capacity planning.

**Done when:** the dashboard shows printer supply alerts, a compliance score per lab, and a one-click "power on lab".

---

## Phase 9: Predictive Maintenance and Anomaly Detection (2-3 weeks)

Only start after you have weeks of real telemetry and repair history.

- [ ] Build a feature table per asset: age, repair count, disk reallocated sectors, temperature trend, uptime pattern, ticket frequency.
- [ ] **Step 1: rule-based risk score** (e.g. SMART warnings plus age plus repeat tickets). Simple, explainable, quick to ship.
- [ ] **Step 2: ML model** (scikit-learn gradient boosting or logistic regression) predicting "likely to fail in 30 days." Run it as a separate Python service called by a scheduled job.
- [ ] Anomaly detection on telemetry and network behavior (rolling z-score or Isolation Forest): a PC offline at 3 AM, sudden traffic spike.
- [ ] "At-risk assets" dashboard with explanations (why was this flagged?).
- [ ] Track precision and recall, and keep a feedback button so staff can mark predictions right or wrong.
- [ ] Be honest in the README about data volume limits; with little data, a rule-based score is the better choice.

**Done when:** the system produces a weekly "assets to inspect" list and you can show how often its predictions were correct.

---

## Phase 10: Observability, Deployment, Portfolio Polish (1-2 weeks)

- [ ] Structured logging (JSON) and centralized error tracking (Sentry).
- [ ] Metrics: Prometheus exporter plus a Grafana dashboard (request latency, queue depth, agents online, scan duration).
- [ ] Health-check endpoint and uptime monitoring for LabTrack itself.
- [ ] Automated daily DB backups with a tested restore procedure.
- [ ] Load test key endpoints (heartbeat ingestion especially) with k6; fix bottlenecks (indexes, batching, caching).
- [ ] Deploy to a VPS or cloud with HTTPS (Let's Encrypt), a reverse proxy, and secrets from environment variables.
- [ ] CD pipeline: tagged release -> build image -> deploy.
- [ ] **Docs:** architecture diagram, ER diagram of the new schema, API docs, ADRs (short notes explaining key decisions), an install guide, and a security/ethics note on scanning.
- [ ] Live demo with seeded data, plus a 2-3 minute walkthrough video.
- [ ] Rewrite the README so every claim is true and verifiable (CSRF, hashing, tests, coverage badge).

---

## Suggested Tech Stack

| Area | Recommended |
|---|---|
| Backend | Laravel (or Slim) |
| Frontend | Blade + Alpine/HTMX, or React/Next.js |
| Database | MySQL 8 (JSON columns); TimescaleDB later if needed |
| Queue/cache | Redis |
| Agent | Python (`psutil`) or Go |
| Network scanning | nmap / python-nmap / scapy, SNMP (`pysnmp`) |
| Realtime | WebSockets (Laravel Reverb) or Server-Sent Events |
| ML | Python, scikit-learn, FastAPI microservice |
| Testing | Pest/PHPUnit, PHPStan |
| DevOps | Docker, GitHub Actions, Prometheus, Grafana, Sentry |

## Rules to Follow Throughout

1. **One phase at a time.** Finish, test, and merge before moving on.
2. **Never skip Phase 2.** Agents, network monitoring, and predictions all depend on assets being properly linked to workstations.
3. **Every feature gets tests and a docs entry** before it counts as done.
4. **Scan only with permission.** Keep logs of what was scanned and when.
5. **Keep commits small and the history clean.** A visible commit history is part of the portfolio.
