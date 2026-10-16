#!/usr/bin/env python3
"""
LabTrack Workstation Monitoring Agent (Phase 6)
Collects host metrics, hardware inventory, and transmits HMAC-authenticated telemetry.
"""

import argparse
import collections
import getpass
import hashlib
import hmac
import json
import logging
import os
import platform
import re
import socket
import subprocess
import sys
import time
import uuid
from typing import Any, Dict, List, Optional, Tuple

try:
    import psutil
except ImportError:
    psutil = None

try:
    import requests
except ImportError:
    requests = None

# Configure safe logging (never log secrets or full tokens)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("labtrack_agent")

CONFIG_FILE_NAME = "agent_config.json"
FAILED_HEARTBEATS_BUFFER: collections.deque = collections.deque(maxlen=100)


def get_config_path() -> str:
    agent_dir = os.path.dirname(os.path.abspath(__file__))
    return os.path.join(agent_dir, CONFIG_FILE_NAME)


def save_config(config_data: Dict[str, Any], path: Optional[str] = None) -> None:
    target_path = path or get_config_path()
    content = json.dumps(config_data, indent=2)

    with open(target_path, "w", encoding="utf-8") as f:
        f.write(content)

    # Apply restricted permissions
    if platform.system() != "Windows":
        try:
            os.chmod(target_path, 0o600)
        except Exception as e:
            logger.warning(f"Could not set 0600 permissions on {target_path}: {e}")
    else:
        # On Windows, try applying icacls so only SYSTEM and Administrators can read
        try:
            subprocess.run(
                [
                    "icacls.exe",
                    target_path,
                    "/inheritance:r",
                    "/grant:r",
                    "*S-1-5-18:(F)",  # SYSTEM
                    "/grant:r",
                    "*S-1-5-32-544:(F)",  # Builtin Administrators
                ],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                check=False,
            )
        except Exception:
            pass


def load_config(path: Optional[str] = None) -> Optional[Dict[str, Any]]:
    target_path = path or get_config_path()
    if not os.path.exists(target_path):
        return None
    try:
        with open(target_path, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception as e:
        logger.error(f"Failed to read agent config: {e}")
        return None


def generate_nonce() -> str:
    """Generate 32-character hex nonce."""
    return uuid.uuid4().hex


def build_canonical_string(
    method: str, path: str, timestamp: int, nonce: str, raw_body: str
) -> str:
    body_hash = hashlib.sha256(raw_body.encode("utf-8")).hexdigest()
    return f"{method.upper()}\n{path}\n{timestamp}\n{nonce}\n{body_hash}"


def compute_signature(
    method: str, path: str, timestamp: int, nonce: str, raw_body: str, secret: str
) -> str:
    canonical = build_canonical_string(method, path, timestamp, nonce, raw_body)
    return hmac.new(
        secret.encode("utf-8"), canonical.encode("utf-8"), hashlib.sha256
    ).hexdigest()


def get_primary_ip_and_mac() -> Tuple[str, str]:
    default_ip = "127.0.0.1"
    default_mac = "00:00:00:00:00:00"

    # Determine primary outbound IP using socket connection probe
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        default_ip = s.getsockname()[0]
        s.close()
    except Exception:
        try:
            default_ip = socket.gethostbyname(socket.gethostname())
        except Exception:
            pass

    # Find matching MAC address from network interfaces
    if psutil:
        try:
            addrs = psutil.net_if_addrs()
            for nic_name, nic_addrs in addrs.items():
                has_ip = any(
                    getattr(a, "address", "") == default_ip for a in nic_addrs
                )
                if has_ip:
                    for a in nic_addrs:
                        addr_str = getattr(a, "address", "")
                        # Look for MAC address formatted as 6 hex pairs
                        if re.match(r"^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$", addr_str):
                            return default_ip, addr_str.upper()

            # Fallback to first non-loopback MAC
            for nic_name, nic_addrs in addrs.items():
                if "loopback" in nic_name.lower():
                    continue
                for a in nic_addrs:
                    addr_str = getattr(a, "address", "")
                    if re.match(r"^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$", addr_str):
                        return default_ip, addr_str.upper()
        except Exception:
            pass

    return default_ip, default_mac


def get_logged_in_user() -> Optional[str]:
    if psutil:
        try:
            users = psutil.users()
            if users:
                return users[0].name
        except Exception:
            pass

    try:
        return os.getlogin()
    except Exception:
        try:
            return getpass.getuser()
        except Exception:
            return None


def get_cpu_temp() -> Optional[float]:
    if psutil and hasattr(psutil, "sensors_temperatures"):
        try:
            temps = psutil.sensors_temperatures()
            if temps:
                for name, entries in temps.items():
                    for entry in entries:
                        if hasattr(entry, "current") and entry.current is not None:
                            return float(entry.current)
        except Exception:
            pass
    return None


def get_smart_health() -> str:
    """
    Check S.M.A.R.T. disk health status.
    Windows: Get-CimInstance MSFT_PhysicalDisk (never use wmic).
    Linux: smartctl (if installed, timeout 5s; report UNKNOWN if missing/failed).
    """
    system = platform.system()

    if system == "Windows":
        cmd = [
            "powershell",
            "-NoProfile",
            "-NonInteractive",
            "-Command",
            "Get-CimInstance -Namespace root/Microsoft/Windows/Storage -ClassName MSFT_PhysicalDisk | "
            "Select-Object -ExpandProperty HealthStatus -ErrorAction SilentlyContinue",
        ]
        try:
            res = subprocess.run(
                cmd, capture_output=True, text=True, timeout=5, check=False
            )
            out = res.stdout.strip().upper()
            if "HEALTHY" in out:
                return "HEALTHY"
            elif "WARNING" in out:
                return "WARNING"
            elif "UNHEALTHY" in out:
                return "FAILING"
        except Exception:
            pass
        return "UNKNOWN"

    elif system == "Linux":
        # Check if smartctl is available
        try:
            res = subprocess.run(
                ["smartctl", "-H", "/dev/sda"],
                capture_output=True,
                text=True,
                timeout=5,
                check=False,
            )
            out = res.stdout.strip().upper()
            if "PASSED" in out or "OK" in out:
                return "HEALTHY"
            elif "FAILED" in out:
                return "FAILING"
            elif "PRE-FAIL" in out or "WARNING" in out:
                return "WARNING"
        except (FileNotFoundError, subprocess.TimeoutExpired, Exception):
            return "UNKNOWN"

    return "UNKNOWN"


def collect_hardware_specs() -> Dict[str, Any]:
    """Collect hardware specs: ram_bytes, disk_bytes, cpu_model, mac_address."""
    specs: Dict[str, Any] = {}

    ip, mac = get_primary_ip_and_mac()
    specs["mac_address"] = mac

    if psutil:
        try:
            vm = psutil.virtual_memory()
            specs["ram_bytes"] = int(vm.total)
        except Exception:
            pass

        try:
            root = "C:\\" if platform.system() == "Windows" else "/"
            du = psutil.disk_usage(root)
            specs["disk_bytes"] = int(du.total)
        except Exception:
            pass

    # CPU model
    system = platform.system()
    if system == "Windows":
        cmd = [
            "powershell",
            "-NoProfile",
            "-NonInteractive",
            "-Command",
            "Get-CimInstance -ClassName Win32_Processor | Select-Object -ExpandProperty Name",
        ]
        try:
            res = subprocess.run(
                cmd, capture_output=True, text=True, timeout=5, check=False
            )
            lines = [line.strip() for line in res.stdout.splitlines() if line.strip()]
            if lines:
                specs["cpu_model"] = lines[0]
        except Exception:
            pass
    elif system == "Linux":
        try:
            with open("/proc/cpuinfo", "r", encoding="utf-8") as f:
                for line in f:
                    if "model name" in line:
                        specs["cpu_model"] = line.split(":", 1)[1].strip()
                        break
        except Exception:
            pass

    if "cpu_model" not in specs:
        specs["cpu_model"] = platform.processor() or "Unknown CPU"

    return specs


def collect_installed_software() -> List[Dict[str, str]]:
    """Collect list of installed software (Windows registry, Linux dpkg/rpm)."""
    software: List[Dict[str, str]] = []
    system = platform.system()

    if system == "Windows":
        ps_cmd = (
            "Get-ItemProperty HKLM:\\Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\* | "
            "Where-Object { $_.DisplayName } | "
            "Select-Object DisplayName, DisplayVersion | ConvertTo-Json -Compress"
        )
        try:
            res = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", ps_cmd],
                capture_output=True,
                text=True,
                timeout=15,
                check=False,
            )
            raw = res.stdout.strip()
            if raw:
                data = json.loads(raw)
                if isinstance(data, dict):
                    data = [data]
                if isinstance(data, list):
                    for item in data:
                        name = item.get("DisplayName")
                        if name:
                            software.append(
                                {
                                    "name": str(name),
                                    "version": str(item.get("DisplayVersion") or "1.0"),
                                }
                            )
        except Exception:
            pass

    elif system == "Linux":
        # Try dpkg-query first
        try:
            res = subprocess.run(
                ["dpkg-query", "-W", "-f=${Package}\t${Version}\n"],
                capture_output=True,
                text=True,
                timeout=10,
                check=False,
            )
            if res.returncode == 0:
                for line in res.stdout.splitlines():
                    parts = line.strip().split("\t", 1)
                    if len(parts) == 2:
                        software.append({"name": parts[0], "version": parts[1]})
        except FileNotFoundError:
            # Try rpm
            try:
                res = subprocess.run(
                    ["rpm", "-qa", "--qf", "%{NAME}\t%{VERSION}\n"],
                    capture_output=True,
                    text=True,
                    timeout=10,
                    check=False,
                )
                if res.returncode == 0:
                    for line in res.stdout.splitlines():
                        parts = line.strip().split("\t", 1)
                        if len(parts) == 2:
                            software.append({"name": parts[0], "version": parts[1]})
            except Exception:
                pass
        except Exception:
            pass

    return software


def collect_metrics() -> Dict[str, Any]:
    """Collect current live metrics."""
    cpu_pct = 0.0
    ram_pct = 0.0
    disk_pct = 0.0
    uptime = 0

    if psutil:
        try:
            cpu_pct = float(psutil.cpu_percent(interval=0.5))
        except Exception:
            pass
        try:
            ram_pct = float(psutil.virtual_memory().percent)
        except Exception:
            pass
        try:
            root = "C:\\" if platform.system() == "Windows" else "/"
            disk_pct = float(psutil.disk_usage(root).percent)
        except Exception:
            pass
        try:
            uptime = max(0, int(time.time() - psutil.boot_time()))
        except Exception:
            pass

    return {
        "cpu_pct": round(min(100.0, max(0.0, cpu_pct)), 2),
        "ram_pct": round(min(100.0, max(0.0, ram_pct)), 2),
        "disk_pct": round(min(100.0, max(0.0, disk_pct)), 2),
        "cpu_temp_c": get_cpu_temp(),
        "uptime_seconds": uptime,
        "logged_in_user": get_logged_in_user(),
        "smart_health": get_smart_health(),
    }


def send_signed_request(
    server_url: str,
    path: str,
    payload: Dict[str, Any],
    api_key_id: str,
    signing_secret: str,
    timeout: int = 10,
) -> Tuple[bool, int, Optional[Dict[str, Any]]]:
    """Transmits HMAC-SHA256 signed request."""
    if requests is None:
        logger.error("requests library is required to send telemetry")
        return False, 0, None

    url = server_url.rstrip("/") + path
    raw_body = json.dumps(payload, separators=(",", ":"))
    timestamp = int(time.time())
    nonce = generate_nonce()

    sig = compute_signature(
        "POST", path, timestamp, nonce, raw_body, signing_secret
    )

    headers = {
        "Content-Type": "application/json",
        "X-Agent-Key-Id": api_key_id,
        "X-Agent-Timestamp": str(timestamp),
        "X-Agent-Nonce": nonce,
        "X-Agent-Signature": sig,
    }

    try:
        resp = requests.post(
            url, data=raw_body.encode("utf-8"), headers=headers, timeout=timeout
        )
        data = None
        try:
            data = resp.json()
        except Exception:
            pass
        return resp.ok, resp.status_code, data
    except Exception as e:
        logger.warning(f"Request to {path} failed: {type(e).__name__}")
        return False, 0, None


def enroll(server_url: str, token: str, hostname: Optional[str] = None) -> bool:
    """Exchange token for agent credentials and save config."""
    if requests is None:
        logger.error("requests library is required for enrollment")
        return False

    url = server_url.rstrip("/") + "/api/agent/enroll"
    host = hostname or socket.gethostname()
    ip, mac = get_primary_ip_and_mac()

    payload = {
        "token": token.strip(),
        "hostname": host,
        "mac_address": mac,
        "ip_address": ip,
        "agent_version": "1.0.0",
    }

    logger.info(f"Enrolling with server {server_url} for host '{host}'...")

    try:
        resp = requests.post(url, json=payload, timeout=10)
        if not resp.ok:
            logger.error(f"Enrollment rejected with HTTP {resp.status_code}")
            return False

        data = resp.json()
        cred = data.get("data", {})
        api_key_id = cred.get("api_key_id")
        signing_secret = cred.get("signing_secret")
        workstation_id = cred.get("workstation_id")

        if not api_key_id or not signing_secret:
            logger.error("Invalid response: missing api_key_id or signing_secret")
            return False

        config = {
            "server_url": server_url.rstrip("/"),
            "workstation_id": workstation_id,
            "api_key_id": api_key_id,
            "signing_secret": signing_secret,
            "hostname": host,
            "enrolled_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        }

        save_config(config)
        logger.info(
            f"Successfully enrolled workstation #{workstation_id}! Credentials saved."
        )
        return True
    except Exception as e:
        logger.error(f"Enrollment network error: {e}")
        return False


def transmit_heartbeat(config: Dict[str, Any]) -> bool:
    """Send heartbeat metrics with buffering on network failure."""
    server = config["server_url"]
    api_key_id = config["api_key_id"]
    secret = config["signing_secret"]

    metrics = collect_metrics()

    # Attempt to send any buffered heartbeats first
    while FAILED_HEARTBEATS_BUFFER:
        buffered = FAILED_HEARTBEATS_BUFFER[0]
        ok, status, _ = send_signed_request(
            server, "/api/agent/heartbeat", buffered, api_key_id, secret
        )
        if ok:
            FAILED_HEARTBEATS_BUFFER.popleft()
        else:
            break

    # Send current metrics
    ok, status, _ = send_signed_request(
        server, "/api/agent/heartbeat", metrics, api_key_id, secret
    )
    if ok:
        logger.info(f"Heartbeat sent (CPU: {metrics['cpu_pct']}%, RAM: {metrics['ram_pct']}%)")
        return True
    else:
        logger.warning(f"Heartbeat transmission failed (HTTP {status}), buffering sample.")
        FAILED_HEARTBEATS_BUFFER.append(metrics)
        return False


def transmit_inventory(config: Dict[str, Any]) -> bool:
    """Send inventory snapshot and receive any open drift alerts."""
    server = config["server_url"]
    api_key_id = config["api_key_id"]
    secret = config["signing_secret"]

    hw = collect_hardware_specs()
    sw = collect_installed_software()

    payload = {
        "reported_hardware": hw,
        "installed_software": sw,
    }

    ok, status, data = send_signed_request(
        server, "/api/agent/inventory", payload, api_key_id, secret, timeout=20
    )
    if ok:
        alerts = (data or {}).get("data", {}).get("open_drift_alerts", [])
        if alerts:
            logger.warning(f"Inventory sent: {len(alerts)} open hardware drift alerts reported!")
        else:
            logger.info("Inventory snapshot recorded successfully.")
        return True
    else:
        logger.warning(f"Inventory transmission failed (HTTP {status})")
        return False


def main() -> None:
    parser = argparse.ArgumentParser(description="LabTrack Telemetry Agent")
    parser.add_argument(
        "--enroll", action="store_true", help="Enroll with the server using a one-time token"
    )
    parser.add_argument("--server", type=str, help="LabTrack server base URL")
    parser.add_argument("--token", type=str, help="One-time enrollment token")
    parser.add_argument("--hostname", type=str, help="Override workstation hostname")
    parser.add_argument("--once", action="store_true", help="Run one cycle and exit")
    parser.add_argument(
        "--inventory", action="store_true", help="Include inventory transmission in --once mode"
    )

    args = parser.parse_args()

    if args.enroll:
        if not args.server or not args.token:
            logger.error("--server and --token are required for --enroll mode")
            sys.exit(1)
        success = enroll(args.server, args.token, args.hostname)
        sys.exit(0 if success else 1)

    config = load_config()
    if not config:
        logger.error("No valid agent configuration found. Run with --enroll first.")
        sys.exit(1)

    if args.once:
        logger.info("Executing single heartbeat cycle (--once)...")
        hb_ok = transmit_heartbeat(config)
        if args.inventory:
            transmit_inventory(config)
        sys.exit(0 if hb_ok else 1)

    # Continuous daemon loop: heartbeat every 60s, inventory every 24h
    logger.info("Starting LabTrack monitoring daemon...")
    last_inventory_time = 0.0

    while True:
        try:
            now = time.time()
            if (now - last_inventory_time) >= 86400:
                transmit_inventory(config)
                last_inventory_time = now

            transmit_heartbeat(config)
        except Exception as e:
            logger.error(f"Unexpected agent loop error: {e}")

        time.sleep(60)


if __name__ == "__main__":
    main()
