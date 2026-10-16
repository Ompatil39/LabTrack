# LabTrack Telemetry & Host Monitoring: Privacy and Data Governance Policy

## 1. Purpose and Scope
The LabTrack Workstation Monitoring Agent (`labtrack_agent.py`) is designed exclusively for institutionally-owned computer laboratories. Its primary purpose is to safeguard hardware inventory, track machine availability, identify equipment failure proactively via S.M.A.R.T. disk monitoring, and detect unauthorized component removal (hardware drift).

> [!IMPORTANT]
> The monitoring agent MUST only be deployed on workstations owned and administered by the educational institution. It must never be deployed on personal student, staff, or faculty devices (BYOD).

## 2. Telemetry Data Collected
The monitoring agent collects metrics across three categories:

### A. Performance and Health Metrics (Operational Telemetry)
- **CPU, RAM, and Disk Utilization Percentages:** 0–100% metrics collected every 60 seconds.
- **CPU Core Temperatures:** Extracted via hardware thermal sensors when supported.
- **System Uptime:** Continuous seconds since last system boot.
- **Storage S.M.A.R.T. Health:** Overall drive condition (`HEALTHY`, `WARNING`, `FAILING`, or `UNKNOWN`).

### B. Hardware & Software Inventory (Drift Detection)
- **Network Identifiers:** Primary IPv4/IPv6 address and physical MAC address.
- **Hardware Profile:** Total RAM capacity, total primary disk capacity, and CPU processor model name.
- **Installed Packages:** List of installed software titles and version numbers (extracted from Windows registry or Linux package managers).

### C. User Attribution (Personal Data Notice)
- **Logged-in User Account (`logged_in_user`):** The local or domain username of the account active at the time of sampling.

> [!CAUTION]
> Under data protection and student privacy regulations, the username of the student or staff member currently active on a laboratory seat constitutes **Personal Data**.
> 
> - Workstations must display a visible login notice / acceptable use banner informing users that session usernames and workstation activity metrics are logged for institutional inventory control and cybersecurity auditing.
> - The agent does NOT log keystrokes, browser history, file contents, screen captures, or peripheral microphones/cameras.

## 3. Data Retention Periods
Telemetry and security artifacts are governed by automated retention pruning:

| Data Type | Retention Period | Justification |
| :--- | :--- | :--- |
| **High-Resolution Samples (`telemetry_samples`)** | 30 Days | Retained for immediate troubleshooting and incident analysis, after which rows are deleted. |
| **Hourly Rollups (`telemetry_hourly_rollups`)** | 365 Days | Aggregated averages and peak usage retained for 1 year for annual capacity planning. |
| **Authentication Nonces (`agent_nonces`)** | 10 Minutes | Retained solely during the active HMAC replay prevention window and continuously pruned. |
| **Hardware Drift Alerts (`hardware_drift_alerts`)** | Indefinite until Resolved | Retained as part of institutional equipment loss and audit documentation. |

The automated retention job is executed via CLI:
```bash
php bin/telemetry-retention.php
```

## 4. Access Control and Visibility
Access to live workstation telemetry, historical metric charts, and hardware drift alerts is strictly role-gated:
1. **System Administrators (`Admin`):** Full read and write authority across all laboratories, enrollment token generation, and drift alert resolution.
2. **Laboratory Incharges (`Incharge`):** Read and resolution authority restricted exclusively to workstations located in laboratories they are assigned to. Attempts to query or modify workstations in other labs return HTTP 403 Forbidden.
3. **General Users & Students:** Zero access to telemetry APIs, live charts, or agent configuration endpoints.
