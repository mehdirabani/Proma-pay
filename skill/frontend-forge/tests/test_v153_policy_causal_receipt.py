import unittest,tempfile,os
from pathlib import Path
from engineering_intelligence.execution_controller import authorize,EngineeringExecutionController
from implementation.causal_patch import generate,validate_causal_patch,CausalPatchError
from planning.plan_attestation import attest_plan
from implementation.change_receipt_verifier import verify_change_receipt

class T(unittest.TestCase):
 def test_l2_cannot_patch(self):
  d=authorize({'status':'PASS'},{'level':'L2_PLAN'},{'operation':'REPAIR'},{'status':'ROOT_CAUSE_CONFIRMED'})
  self.assertFalse(d['authorized'])
 def test_diagnostic_plan_cannot_patch(self):
  d=authorize({'status':'DIAGNOSTIC_PLAN_ONLY'},{'level':'L4_IMPLEMENT_AND_VALIDATE'},{'operation':'REPAIR'},{'status':'ROOT_CAUSE_CONFIRMED'})
  self.assertFalse(d['authorized'])
 def test_probable_root_cause_cannot_patch(self):
  d=authorize({'status':'PASS'},{'level':'L3_IMPLEMENT_LOW_RISK'},{'operation':'REPAIR'},{'status':'ROOT_CAUSE_PROBABLE'})
  self.assertFalse(d['authorized'])
 def test_causal_patch_requires_cause(self):
  with self.assertRaises(CausalPatchError):generate({'task':{'operation':'REPAIR'},'target':'A.css','root_cause':{'status':'ROOT_CAUSE_UNVERIFIED'},'change_plan':{'status':'PASS'}},'.x{min-width:600px;}')
 def test_wrong_cause_rejects_unrelated_patch(self):
  rc={'status':'ROOT_CAUSE_CONFIRMED','root_cause':{'hypothesis_id':'h','cause_code':'MIN_WIDTH_FIXED'}}
  op={'operation':'MODIFY','root_cause_id':'h','mechanism':'COLOR'}
  self.assertEqual(validate_causal_patch(op,rc)['status'],'PATCH_CAUSE_MISMATCH')
 def test_signed_change_receipt_verifies_and_fake_fails(self):
  with tempfile.TemporaryDirectory() as d:
   root=Path(d);state=root/'.state';os.environ['FFX_RUNTIME_STATE_HOME']=str(state);(root/'A.css').write_text('.x{min-width:600px;}')
   rc={'status':'ROOT_CAUSE_CONFIRMED','root_cause':{'hypothesis_id':'h','cause_code':'MIN_WIDTH_FIXED'}}
   plan=attest_plan({'status':'PASS','plan_id':'p','expected_effect':['responsive']})
   op=generate({'task':{'operation':'REPAIR'},'target':'A.css','root_cause':rc,'change_plan':plan,'evidence':[]},(root/'A.css').read_text())
   r=EngineeringExecutionController().execute(root,[op],plan=plan,autonomy={'level':'L3_IMPLEMENT_LOW_RISK'},task_model={'operation':'REPAIR'},root_cause=rc,receipt_context={'task_id':'t','session_id':'s','plan_id':'p','target_evidence':[],'state_dir':str(state)},mode='PRODUCTION')
   self.assertEqual(r['status'],'PASS')
   receipt=r['change_receipt'];self.assertTrue(verify_change_receipt(receipt,root,task_id='t',session_id='s',transaction_id=r['txid'],state_dir=str(state))[0])
   bad=dict(receipt);bad['signature']='not-real';self.assertFalse(verify_change_receipt(bad,root,task_id='t',state_dir=str(state))[0])
if __name__=='__main__':unittest.main()
