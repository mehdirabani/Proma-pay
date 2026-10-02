import unittest
from debugging.hypothesis_engine import build_hypotheses
from debugging.root_cause_engine import determine_root_cause
from debugging.failure_attribution import attribute_failures
from planning.change_planner import plan_change
from impact.impact_predictor import predict,evaluate_prediction
class T(unittest.TestCase):
 def test_root_cause_not_first_guess_only(self):
  h=build_hypotheses('mobile overflow min-width',['overflow','min-width']);r=determine_root_cause(h,['overflow','min-width']);self.assertEqual(r['status'],'ROOT_CAUSE_CONFIRMED')
 def test_failure_attribution(self):self.assertEqual(attribute_failures(['old'],['old','new'])['classification'],'PATCH_FAILURE')
 def test_plan_blocked_without_confirmed_target(self):
  p=plan_change({'goal':'x','risk':'LOW','task_type':'STYLE_CHANGE','acceptance_criteria':[]},{},{'state':'TARGET_PROBABLE'});self.assertEqual(p['status'],'BLOCKED')
 def test_impact(self):
  g={'A':{'consumers':['B'],'tests':['A.test']},'B':{'consumers':['C']}}
  p=predict(['A'],g);self.assertIn('B',p['direct']);self.assertIn('C',p['indirect']);self.assertEqual(evaluate_prediction(['B'],['B'])['precision'],1)
