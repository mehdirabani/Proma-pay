import unittest,tempfile,os
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from engineering_evidence.observation_broker import EngineeringObservationBroker
from engineering_evidence.verifier_v3 import verify_observation,verify_evidence
from provenance.central_authority import get_central_authority

class T(unittest.TestCase):
 def setUp(self):
  self.t=tempfile.TemporaryDirectory();self.root=Path(self.t.name);os.environ['FFX_RUNTIME_STATE_HOME']=str(self.root/'.state')
  (self.root/'A.tsx').write_text('export const A=()=> <div>checkout</div>')
  (self.root/'Use.tsx').write_text("import {A} from './A'; export const U=()=> <A/>")
  self.idx=RepositoryIndexer(self.root).scan()
 def tearDown(self):self.t.cleanup()
 def test_raw_issue_blocked(self):
  b=EngineeringObservationBroker(self.root,'r','t','s',self.idx)
  with self.assertRaises(PermissionError):b.issue('x')
 def test_real_observation_signed_and_verifies(self):
  b=EngineeringObservationBroker(self.root,'r','t','s',self.idx)
  x=b.observe('static-task-link-collector','A.tsx',{'task':'fix checkout'})
  self.assertTrue(verify_observation(x['observation_receipt'],workspace=self.root,revision='r',task_id='t',candidate='A.tsx',state_dir=str(self.root/'.state'))[0])
  self.assertTrue(verify_evidence(x['evidence'],workspace=self.root,revision='r',task_id='t',candidate='A.tsx',state_dir=str(self.root/'.state'))[0])
 def test_missing_artifact_blocked(self):
  b=EngineeringObservationBroker(self.root,'r','t','s',self.idx)
  with self.assertRaises(FileNotFoundError):b.observe('static-task-link-collector','Missing.tsx',{'task':'fix checkout'})
 def test_source_id_not_caller_controlled(self):
  b=EngineeringObservationBroker(self.root,'r','t','s',self.idx)
  x=b.observe('static-task-link-collector','A.tsx',{'task':'fix checkout'},request={'source_id':'attacker:fake'})
  self.assertNotIn('attacker',x['evidence']['source_id'])
 def test_broker_acl_blocks_wrong_record_type(self):
  c=get_central_authority(str(self.root/'.state')).broker('engineering-observation-broker')
  r=c.sign({'record_type':'engineering_change'})
  self.assertEqual(r['error'],'BROKER_ACL_DENIED')
 def test_stale_after_artifact_change(self):
  b=EngineeringObservationBroker(self.root,'r','t','s',self.idx)
  x=b.observe('static-task-link-collector','A.tsx',{'task':'fix checkout'})
  (self.root/'A.tsx').write_text('changed')
  self.assertEqual(verify_evidence(x['evidence'],workspace=self.root,revision='r',task_id='t',candidate='A.tsx',state_dir=str(self.root/'.state'))[1],'OBSERVATION_STALE')
if __name__=='__main__':unittest.main()
