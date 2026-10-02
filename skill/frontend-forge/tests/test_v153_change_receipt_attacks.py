import unittest,tempfile,os
from pathlib import Path
from implementation.patch_engine import apply_change_plan
from planning.plan_attestation import attest_plan
from implementation.change_receipt_verifier import verify_change_receipt
class T(unittest.TestCase):
 def make(self):
  td=tempfile.TemporaryDirectory();root=Path(td.name);state=root/'.state';os.environ['FFX_RUNTIME_STATE_HOME']=str(state);(root/'x.txt').write_text('a')
  plan=attest_plan({'status':'PASS','plan_id':'p'});rc={'status':'ROOT_CAUSE_CONFIRMED','root_cause':{'hypothesis_id':'h','cause_code':'MIN_WIDTH_FIXED'}};auto={'level':'L3_IMPLEMENT_LOW_RISK'};pol={'authorized':True,'status':'PASS'}
  r=apply_change_plan(root,[{'operation':'MODIFY','path':'x.txt','old':'a','new':'b'}],mode='PRODUCTION',receipt_context={'task_id':'t','session_id':'s','plan':plan,'root_cause_record':rc,'target_evidence':[],'autonomy_decision':auto,'execution_policy':pol,'state_dir':str(state)})
  return td,root,state,r
 def test_wrong_task_rejected(self):
  td,root,state,r=self.make()
  try:self.assertEqual(verify_change_receipt(r['change_receipt'],root,task_id='other',state_dir=str(state))[1],'CHANGE_TASK_MISMATCH')
  finally:td.cleanup()
 def test_wrong_transaction_rejected(self):
  td,root,state,r=self.make()
  try:self.assertEqual(verify_change_receipt(r['change_receipt'],root,transaction_id='other',state_dir=str(state))[1],'CHANGE_TRANSACTION_MISMATCH')
  finally:td.cleanup()
 def test_wrong_revision_rejected(self):
  td,root,state,r=self.make()
  try:self.assertEqual(verify_change_receipt(r['change_receipt'],root,revision_after='bad',state_dir=str(state))[1],'CHANGE_REVISION_MISMATCH')
  finally:td.cleanup()
if __name__=='__main__':unittest.main()
