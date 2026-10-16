import os
import sys
import unittest
from unittest.mock import MagicMock, patch

# Ensure agent directory is in sys.path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import labtrack_agent


class TestLabTrackAgent(unittest.TestCase):

    def test_signature_matches_known_test_vector(self):
        """
        Verify that HMAC signature matches the identical test vector in Phase6AgentTelemetryTest.php.
        """
        method = "POST"
        path = "/api/agent/heartbeat"
        timestamp = 1700000000
        nonce = "a1b2c3d4e5f60718293a4b5c6d7e8f90"
        raw_body = '{"cpu_pct":25.5,"ram_pct":60.0,"disk_pct":45.2}'
        secret = "my-test-signing-secret-key-32chars!!"

        canonical = labtrack_agent.build_canonical_string(
            method, path, timestamp, nonce, raw_body
        )
        expected_canonical = (
            "POST\n"
            "/api/agent/heartbeat\n"
            "1700000000\n"
            "a1b2c3d4e5f60718293a4b5c6d7e8f90\n"
            "250291a688d644b7aaba0a872683b560d6fd287d2a2d8517e4fd0c7b2c4df0c9"
        )
        self.assertEqual(canonical, expected_canonical)

        sig = labtrack_agent.compute_signature(
            method, path, timestamp, nonce, raw_body, secret
        )
        expected_sig = "40f207d2899bdd3a034a90e490780f1424f898b7b6d04611cc0dc19ae2f316e2"
        self.assertEqual(sig, expected_sig)

    def test_nonce_is_fresh_per_call(self):
        """Ensure generate_nonce generates unique 32-character hex nonces."""
        nonces = [labtrack_agent.generate_nonce() for _ in range(50)]
        # All nonces must be 32 hex characters
        for n in nonces:
            self.assertEqual(len(n), 32)
            self.assertTrue(all(c in "0123456789abcdef" for c in n.lower()))
        # All nonces must be unique
        self.assertEqual(len(nonces), len(set(nonces)))

    @patch("platform.system", return_value="Linux")
    @patch("subprocess.run", side_effect=FileNotFoundError("smartctl not found"))
    def test_missing_smartctl_gives_unknown(self, mock_run, mock_system):
        """Test that a missing smartctl tool safely reports UNKNOWN without raising errors."""
        status = labtrack_agent.get_smart_health()
        self.assertEqual(status, "UNKNOWN")

    def test_buffering_caps_at_100(self):
        """Test that failed heartbeat buffer caps at exactly 100 samples."""
        labtrack_agent.FAILED_HEARTBEATS_BUFFER.clear()

        # Append 150 samples
        for i in range(150):
            sample = {"cpu_pct": float(i), "ram_pct": 50.0, "disk_pct": 20.0}
            labtrack_agent.FAILED_HEARTBEATS_BUFFER.append(sample)

        self.assertEqual(len(labtrack_agent.FAILED_HEARTBEATS_BUFFER), 100)
        # Oldest samples should have been discarded (first element should be sample 50)
        self.assertEqual(labtrack_agent.FAILED_HEARTBEATS_BUFFER[0]["cpu_pct"], 50.0)
        self.assertEqual(labtrack_agent.FAILED_HEARTBEATS_BUFFER[-1]["cpu_pct"], 149.0)

    @patch("requests.post")
    def test_transmit_heartbeat_buffers_on_failure(self, mock_post):
        """Test that transmit_heartbeat buffers failed attempts when server is unreachable."""
        labtrack_agent.FAILED_HEARTBEATS_BUFFER.clear()
        mock_post.side_effect = Exception("Connection refused")

        config = {
            "server_url": "http://127.0.0.1:8000",
            "api_key_id": "test-key-id",
            "signing_secret": "test-secret",
        }

        with patch("labtrack_agent.collect_metrics", return_value={"cpu_pct": 10.0, "ram_pct": 20.0, "disk_pct": 30.0}):
            result = labtrack_agent.transmit_heartbeat(config)
            self.assertFalse(result)
            self.assertEqual(len(labtrack_agent.FAILED_HEARTBEATS_BUFFER), 1)
            self.assertEqual(labtrack_agent.FAILED_HEARTBEATS_BUFFER[0]["cpu_pct"], 10.0)


if __name__ == "__main__":
    unittest.main()
