import unittest
from repair_v15.repair_loop import run_repair
from review.self_review import self_review
from review.adversarial import adversarial_scenarios
from refactor_v15.safety import refactor_policy,invariants
class T(unittest.TestCase):
 def test_repair_bounded(self):
  calls={'n':0}
  def v():return {'status':'FAIL'}
  def r(x,i):calls['n']+=1;return {'status':'DONE'}
  out=run_repair(v,r,3);self.assertEqual(out['status'],'REPAIR_LIMIT_REACHED');self.assertEqual(calls['n'],3)
 def test_refactor_requires_characterization(self):self.assertEqual(refactor_policy(.2,invariants())['status'],'CHARACTERIZATION_TEST_REQUIRED')
 def test_self_review_rejects_unconfirmed_root(self):
  r=self_review({}, {'status':'ROOT_CAUSE_UNVERIFIED'},{'unrelated_changes':[],'anti_pattern_flags':[]},[]);self.assertEqual(r['status'],'REVIEW_FAILED')
 def test_risk_adaptive_adversarial(self):self.assertIn('mobile',adversarial_scenarios({'domains':['responsive']}))
