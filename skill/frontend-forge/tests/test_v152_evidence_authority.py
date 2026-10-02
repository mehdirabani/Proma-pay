import unittest,tempfile,os
from pathlib import Path
from engineering_evidence.broker import EngineeringEvidenceBroker,create_unattested_evidence
from engineering_evidence.verifier import verify_engineering_evidence
from engineering_intelligence.target_safety import assess_target
class TestEvidenceAuthority(unittest.TestCase):
 def setUp(self):
  self.t=tempfile.TemporaryDirectory();self.root=Path(self.t.name);(self.root/"A.tsx").write_text("export const A=()=> <div>checkout</div>")
  os.environ["FFX_RUNTIME_STATE_HOME"]=str(self.root/".state")
 def tearDown(self):self.t.cleanup()
 def test_unsigned_prod_rejected(self):
  e=create_unattested_evidence(candidate="A.tsx")
  ok,reason=verify_engineering_evidence(e,workspace=self.root,revision="r",task_id="t",candidate="A.tsx");self.assertFalse(ok)
 def test_broker_attestation_verifies(self):
  b=EngineeringEvidenceBroker(self.root,"r","t","s")
  try:
   raw={"claim_type":"TASK_TARGET_LINK","observation":"checkout task found in target","source_artifact":"A.tsx","source_id":"repository-static:task-link","directness":.9,"metadata":{"overlap":["checkout"]}}
   e=b.issue("static-task-link-collector",raw,"fix checkout","A.tsx")
   self.assertTrue(verify_engineering_evidence(e,workspace=self.root,revision="r",task_id="t",candidate="A.tsx")[0])
  finally:b.close()
 def test_hash_only_forgery_rejected(self):
  forged={"record_type":"engineering_evidence","signature":"fake"}
  self.assertFalse(verify_engineering_evidence(forged,workspace=self.root,revision="r",task_id="t",candidate="A.tsx")[0])
