import json
import os
import random
import time
import urllib.request

API_URL = os.getenv("SIMULATOR_API_URL", "http://app/api/v1/telemetry.php")
AGENT_KEY = os.getenv("AGENT_INGEST_KEY", "dev_lab_agent_secret_key")
INTERVAL_SECONDS = int(os.getenv("SIMULATOR_INTERVAL_SECONDS", "5"))

NODES = []
for index in range(1, 11):
    NODES.append(
        {
            "hostname": f"LAB1-PC{index:02d}",
            "mac_address": f"02:1B:44:01:{index:02X}:{index + 16:02X}",
            "ip_address": f"192.168.1.{100 + index}",
            "base_cpu": random.uniform(20, 50),
            "base_ram": random.uniform(35, 70),
            "base_disk": random.uniform(30, 65),
        }
    )


def clamp(value, minimum=0.0, maximum=100.0):
    return max(minimum, min(maximum, value))


def send_payload(payload):
    data = json.dumps(payload).encode("utf-8")
    request = urllib.request.Request(
        API_URL,
        data=data,
        headers={
            "Content-Type": "application/json",
            "X-Agent-Key": AGENT_KEY,
        },
        method="POST",
    )

    with urllib.request.urlopen(request, timeout=10) as response:
        return response.status, response.read().decode("utf-8")


print(f"Starting LabTrack simulator -> {API_URL}")

while True:
    spike_node = random.choice(NODES)
    unplug_node = random.choice(NODES)

    for node in NODES:
        cpu_usage = clamp(node["base_cpu"] + random.uniform(-8, 8))
        ram_usage = clamp(node["base_ram"] + random.uniform(-6, 6))
        disk_usage = clamp(node["base_disk"] + random.uniform(-2, 2))

        if node is spike_node:
            cpu_usage = random.uniform(91, 98)

        peripherals = {
            "keyboard": True,
            "mouse": node is not unplug_node,
            "usb_storage_connected": random.random() < 0.2,
        }

        payload = {
            "lab_id": "LAB001",
            "mac_address": node["mac_address"],
            "hostname": node["hostname"],
            "ip_address": node["ip_address"],
            "cpu_usage": round(cpu_usage, 2),
            "ram_usage": round(ram_usage, 2),
            "disk_usage": round(disk_usage, 2),
            "network_latency_ms": random.randint(6, 45),
            "active_user": f"student_{node['hostname'][-2:]}",
            "peripherals": peripherals,
        }

        try:
            status_code, body = send_payload(payload)
            print(f"[{node['hostname']}] {status_code} {body}")
        except Exception as exc:
            print(f"[{node['hostname']}] send failed: {exc}")

    time.sleep(INTERVAL_SECONDS)
