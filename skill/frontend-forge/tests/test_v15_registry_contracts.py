import unittest,json
from runtime_py.io_utils import ROOT
from runtime_py.capability_executor import execute_capability
class T(unittest.TestCase):
 def test_v15_caps_registered(self):
  r=json.loads((ROOT/'runtime/capability-registry.json').read_text())['capabilities'];
  for c in ['engineering-task-analyzer','target-safety-gate','root-cause-engine-v15','change-planner-v15','impact-predictor-v15']:self.assertIn(c,r)
 def test_task_cap_executes(self):
  r=execute_capability('engineering-task-analyzer',{'task':'Fix mobile overflow'});self.assertEqual(r['status'],'PASS')
 def test_safety_cap_executes(self):
  r=execute_capability('target-safety-gate',{'candidate':{'path':'A','confidence':.8},'task_evidence':True,'dependency_evidence':True});self.assertEqual(r['status'],'PASS');self.assertEqual(r['result']['target_safety']['state'],'INVALID_TARGET_EVIDENCE')
