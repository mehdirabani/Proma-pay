import unittest,tempfile,os
from pathlib import Path
from implementation.patch_engine import apply_change_plan
from implementation.reserved_paths import validate_patch_path
from provenance.verifier import verify_signed
class TestPatchReceipt(unittest.TestCase):
 def test_reserved_path_blocked(self):
  with self.assertRaises(PermissionError):validate_patch_path(".ffx-transactions/x")
 def test_signed_change_receipt(self):
  with tempfile.TemporaryDirectory() as d:
   root=Path(d);os.environ["FFX_RUNTIME_STATE_HOME"]=str(root/".state");(root/"a.txt").write_text("old")
   r=apply_change_plan(root,[{"operation":"MODIFY","path":"a.txt","old":"old","new":"new"}],mode="PRODUCTION",
     receipt_context={"task_id":"t","session_id":"s","plan_id":"p","root_cause_id":"r","target_evidence_ids":[],"state_dir":str(root/".state")})
   self.assertEqual(r["status"],"PASS");self.assertTrue(r["change_receipt"]["signature"]);self.assertTrue(verify_signed(r["change_receipt"],state_dir=str(root/".state")))
