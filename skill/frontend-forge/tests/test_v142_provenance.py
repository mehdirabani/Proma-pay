import unittest,tempfile,json,time
from pathlib import Path
from provenance.authority import ProvenanceAuthorityClient,UnauthorizedProvenanceIssuer,issue_execution_receipt
from provenance.execution_broker import ExecutionBroker
from provenance.verifier import verify_signed
from provenance.replay_protection import validate_receipt_binding
from security.sandbox_policy import SandboxPolicy

class TestV142Provenance(unittest.TestCase):
    def test_direct_authority_creation_blocked(self):
        with self.assertRaises(UnauthorizedProvenanceIssuer):ProvenanceAuthorityClient()
    def test_legacy_direct_issue_blocked(self):
        with self.assertRaises(UnauthorizedProvenanceIssuer):issue_execution_receipt("lighthouse")
    def _receipt(self,d,sid="s1",task="t1",rev="r1"):
        b=ExecutionBroker()
        try:
            r=b.execute(session_id=sid,task_id=task,capability_id="git-status",adapter_id="git",actor="git",
                argv=["git","--version"],policy=SandboxPolicy(d,trust_level="PROJECT_TRUSTED",require_full_isolation=False),
                runtime_mode="LOCAL",project_revision=rev,timeout=5)
            return r["execution_receipt"]
        finally:b.close()
    def test_broker_receipt_signed(self):
        with tempfile.TemporaryDirectory() as d:self.assertTrue(verify_signed(self._receipt(d)))
    def test_cross_session_replay_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            r=self._receipt(d)
            ok,reason=validate_receipt_binding(r,session_id="other",task_id="t1",project_revision="r1",workspace=d,capability_id="git-status")
            self.assertFalse(ok);self.assertEqual(reason,"SESSION_MISMATCH")
    def test_cross_revision_replay_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            r=self._receipt(d)
            ok,reason=validate_receipt_binding(r,session_id="s1",task_id="t1",project_revision="r2",workspace=d,capability_id="git-status")
            self.assertFalse(ok);self.assertEqual(reason,"REVISION_MISMATCH")
    def test_cross_workspace_replay_rejected(self):
        with tempfile.TemporaryDirectory() as d,tempfile.TemporaryDirectory() as d2:
            r=self._receipt(d)
            ok,reason=validate_receipt_binding(r,session_id="s1",task_id="t1",project_revision="r1",workspace=d2,capability_id="git-status")
            self.assertFalse(ok);self.assertEqual(reason,"WORKSPACE_MISMATCH")

    def test_adapter_identity_cannot_be_forged_with_python(self):
        import tempfile
        from provenance.execution_broker import ExecutionBroker
        from security.sandbox_policy import SandboxPolicy
        with tempfile.TemporaryDirectory() as d:
            b=ExecutionBroker()
            try:r=b.execute(session_id="s",task_id="t",capability_id="lighthouse-check",adapter_id="lighthouse",actor="lighthouse",argv=["python","-c","print(1)"],policy=SandboxPolicy(d,trust_level="PROJECT_TRUSTED",require_full_isolation=False),project_revision="r")
            finally:b.close()
            self.assertEqual(r["status"],"SECURITY_BLOCKED")
