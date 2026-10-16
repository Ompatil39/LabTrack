import hashlib
import hmac

method = "POST"
path = "/api/agent/heartbeat"
timestamp = "1700000000"
nonce = "a1b2c3d4e5f60718293a4b5c6d7e8f90"
body = '{"cpu_pct":25.5,"ram_pct":60.0,"disk_pct":45.2}'
secret = "my-test-signing-secret-key-32chars!!"

body_hash = hashlib.sha256(body.encode('utf-8')).hexdigest()
canonical = f"{method}\n{path}\n{timestamp}\n{nonce}\n{body_hash}"
signature = hmac.new(secret.encode('utf-8'), canonical.encode('utf-8'), hashlib.sha256).hexdigest()

print("--- PYTHON HMAC VECTOR ---")
print(f"Method:    {method}")
print(f"Path:      {path}")
print(f"Timestamp: {timestamp}")
print(f"Nonce:     {nonce}")
print(f"Body:      {body}")
print(f"Body SHA:  {body_hash}")
print(f"Secret:    {secret}")
print(f"Canonical:\n{canonical}")
print(f"Signature: {signature}")
