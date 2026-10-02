import unittest,inspect
from benchmarks.engineering_v152_blind.runner import run
import benchmarks.engineering_v152_blind.execution as execution
class T(unittest.TestCase):
 def test_blind_pipeline(self):
  r=run();self.assertEqual(r['solved'],r['tasks']);self.assertEqual(r['measurement'],'VERIFIED_BLIND_E2E_FIXTURE')
 def test_execution_has_no_ground_truth_access(self):
  src=inspect.getsource(execution);self.assertNotIn('sealed_evaluation',src);self.assertNotIn('must_contain',src);self.assertNotIn('expected_patch',src)
