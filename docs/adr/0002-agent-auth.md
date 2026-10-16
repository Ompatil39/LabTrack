# ADR 0002: Monitoring Agent Authentication & Cryptographic Verification

## Status
Accepted

## Context
In LabTrack Phase 6, computer lab workstations run an autonomous monitoring agent (`labtrack_agent.py`) reporting live hardware metrics (`/api/agent/heartbeat`) and software inventories (`/api/agent/inventory`). Because these endpoints receive data directly from untrusted operating systems across academic lab networks, standard session cookies or static API tokens are insufficient:
1. Academic LANs can be subject to sniffing, ARP poisoning, or packet capture.
2. Long-lived plaintext API keys stored in database tables or sent in HTTP headers risk exposure via logs, proxy dumps, or database exfiltration.
3. Automated telemetry endpoints are susceptible to replay attacks, message tampering, and spoofing.

## Decision

### 1. Ephemeral Enrollment & Key Pair Exchange
- **One-Time Token Enrollment:** Workstations do not share hardcoded credentials. An administrator generates a cryptographically random token (`random_bytes(32)`) via `POST /workstations/{id}/enroll-token`. Only the SHA-256 digest is stored with a 24-hour expiration window.
- **Recoverable Signing Secret with Envelope Encryption:** Upon first contact (`POST /api/agent/enroll`), the token is consumed atomically. The server generates an `api_key_id` (UUID v4) and a `signing_secret` (256-bit hex). The server stores the secret encrypted with AES-256-GCM using the server's master `APP_KEY` or `AGENT_SECRET_KEY`. No long-lived secret is stored in plaintext.

### 2. Request Signing (HMAC-SHA256)
Every telemetry request must include:
- `X-Agent-Key-Id`: Public identifier mapping to the workstation's encrypted credential.
- `X-Agent-Timestamp`: Current Unix epoch seconds.
- `X-Agent-Nonce`: 32-character hexadecimal unique identifier generated per request.
- `X-Agent-Signature`: Hex-encoded HMAC-SHA256 signature.

The canonical string signed by the agent:
```
METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + hex(sha256(raw_body))
```

### 3. Replay Protection (Nonce & Clock Drift Window)
- **Timestamp Tolerance:** Requests where `|server_time - timestamp| > 300 seconds` are rejected with HTTP 401. This restricts replay windows.
- **Nonce Ledger:** Nonces are recorded in `agent_nonces (nonce, api_key_id, created_at)`. Duplicate nonces for an agent are immediately rejected (HTTP 401).
- **Pruning:** Nonces older than 10 minutes are purged automatically.

### 4. Mandatory HTTPS in Production
While the HMAC signature prevents tampering and request modification, **HTTPS is mandatory in production** to:
1. Protect the initial one-time enrollment exchange where `api_key_id` and `signing_secret` are returned to the agent.
2. Protect privacy: Telemetry contains the `logged_in_user` username and active IP/MAC addresses (confidential personal and operational data).
3. Prevent passive monitoring of lab workstation usage patterns.

## Consequences
- **Positive:** Robust defense against spoofing, body tampering, replay attacks, and credential exfiltration.
- **Positive:** Revocation is instantaneous (`is_revoked = 1` in `agent_credentials`).
- **Operational:** Workstation agents must maintain reasonably synchronized system clocks (NTP).
