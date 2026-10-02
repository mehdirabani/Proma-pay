import unittest,tempfile,os
from pathlib import Path
from security.command_policy import validate_command,CommandPolicyError
from security.path_policy import resolve_in_workspace,PathPolicyError
from security.secret_detector import redact
from security.environment_policy import sanitized_env

class SecurityTests(unittest.TestCase):
    def test_disallowed_command(self):
        with self.assertRaises(CommandPolicyError):validate_command(["bash","-c","echo x"])
    def test_command_injection_semicolon(self):
        with self.assertRaises(CommandPolicyError):validate_command(["npm","run","build;rm -rf /"])
    def test_command_injection_pipe(self):
        with self.assertRaises(CommandPolicyError):validate_command(["git","status|cat"])
    def test_path_traversal(self):
        with tempfile.TemporaryDirectory() as d:
            with self.assertRaises(PathPolicyError):resolve_in_workspace(d,"../../etc/passwd")
    def test_safe_path(self):
        with tempfile.TemporaryDirectory() as d:self.assertTrue(str(resolve_in_workspace(d,"a.txt")).startswith(str(Path(d).resolve())))
    def test_redacts_api_key(self):self.assertNotIn("ABCDEFGH123456",redact("api_key=ABCDEFGH123456"))
    def test_redacts_bearer(self):self.assertNotIn("secret.token.value",redact("Authorization: Bearer secret.token.value"))
    def test_private_key_redacted(self):
        x="-----BEGIN PRIVATE KEY-----\nABCDEF\n-----END PRIVATE KEY-----";self.assertNotIn("ABCDEF",redact(x))
    def test_malicious_package_script_blocked(self):
        from adapters.registry import get_adapter
        with tempfile.TemporaryDirectory() as d:
            Path(d,"package.json").write_text('{"scripts":{"build":"node ok.js; rm -rf /"}}')
            r=get_adapter("build").execute({"workspace":d,"script":"build"})
            self.assertEqual(r["status"],"SECURITY_BLOCKED")
    def test_env_secrets_removed(self):
        e=sanitized_env({"PATH":"/x","API_TOKEN":"secret","NODE_ENV":"test"});self.assertNotIn("API_TOKEN",e);self.assertIn("PATH",e)
