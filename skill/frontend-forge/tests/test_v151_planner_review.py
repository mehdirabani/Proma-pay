import unittest
from planning.change_planner import plan_change
from review.self_review import self_review
class T(unittest.TestCase):
 def tm(self):return {'goal':'fix bug','task_type':'BUG_FIX','risk':'LOW','domains':['responsive'],'acceptance_criteria':['works']}
 def test_planner_blocks_unverified_root(self):
  p=plan_change(self.tm(),{'status':'ROOT_CAUSE_UNVERIFIED'},{'state':'TARGET_CONFIRMED','target':'A.css'});self.assertEqual(p['status'],'DIAGNOSTIC_PLAN_ONLY')
 def test_root_cause_operation_contract(self):
  rc={'status':'ROOT_CAUSE_CONFIRMED','root_cause':{'hypothesis_id':'h1','hypothesis':'min width'}}
  p=plan_change(self.tm(),rc,{'state':'TARGET_CONFIRMED','target':'A.css'});self.assertEqual(p['status'],'PASS');self.assertEqual(p['operations'][0]['root_cause_id'],'h1');self.assertIn('reason',p['operations'][0])
 def test_timeout_blocks_review(self):
  r=self_review(self.tm(),{'status':'ROOT_CAUSE_CONFIRMED'},{'unrelated_changes':[],'anti_pattern_flags':[]},[{'status':'TOOL_TIMEOUT'}]);self.assertEqual(r['status'],'REVIEW_BLOCKED')
 def test_unverified_incomplete(self):
  r=self_review({'task_type':'STYLE_CHANGE'},{'status':'ROOT_CAUSE_UNVERIFIED'},{'unrelated_changes':[],'anti_pattern_flags':[]},[{'status':'UNVERIFIED'}]);self.assertEqual(r['status'],'REVIEW_INCOMPLETE')
 def test_unknown_fails_closed(self):
  r=self_review({'task_type':'STYLE_CHANGE'},{'status':'ROOT_CAUSE_UNVERIFIED'},{'unrelated_changes':[],'anti_pattern_flags':[]},[{'status':'MAGIC_PASS'}]);self.assertEqual(r['status'],'REVIEW_FAILED')
