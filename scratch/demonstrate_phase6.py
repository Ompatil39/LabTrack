import json
import os
import sys
import time
import requests
import pymysql

BASE_URL = "http://127.0.0.1:8088"

# Connect to database directly for state inspection
db = pymysql.connect(
    host="127.0.0.1",
    user="labtrack_user",
    password="labtrack_secret",
    database="labtrack",
    cursorclass=pymysql.cursors.DictCursor
)

def run_query(sql, params=None):
    with db.cursor() as cur:
        cur.execute(sql, params or ())
        return cur.fetchall()

def run_exec(sql, params=None):
    with db.cursor() as cur:
        cur.execute(sql, params or ())
        db.commit()

print("--- Step 0: Ensure Workstation 5 is ready for enrollment ---")
run_exec("DELETE FROM hardware_drift_alerts WHERE workstation_id = 5")
run_exec("DELETE FROM workstation_inventory_snapshots WHERE workstation_id = 5")
run_exec("DELETE FROM telemetry_samples WHERE workstation_id = 5")
run_exec("DELETE FROM agent_credentials WHERE workstation_id = 5")

# Generate enrollment token for ws 5 directly using PHP CLI helper
token = "testtoken_ws5_" + os.urandom(16).hex()
token_hash = os.popen(f"php -r \"echo hash('sha256', '{token}');\"").read().strip()
run_exec(
    "INSERT INTO agent_credentials (workstation_id, enrollment_token_hash, enrollment_expires_at, is_revoked) "
    "VALUES (5, %s, DATE_ADD(NOW(), INTERVAL 24 HOUR), 0)",
    (token_hash,)
)
print(f"Generated token for WS 5: {token}")

with open("scratch/token_ws5.txt", "w") as f:
    f.write(token)

print("Ready.")
