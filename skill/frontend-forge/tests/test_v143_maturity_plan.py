import unittest
from runtime_py.maturity_gate import ensure_capability_ready
from runtime_py.plan_validation import validate_plan_graph
class T(unittest.TestCase):
 def test_reference_blocked(self):self.assertEqual(ensure_capability_ready('sleep-engine','PRODUCTION',True)['status'],'CAPABILITY_NOT_READY')
 def test_verified_allowed(self):self.assertEqual(ensure_capability_ready('planner','PRODUCTION',True)['status'],'PASS')
 def test_missing_dep(self):self.assertEqual(validate_plan_graph({'steps':[{'id':'a','depends_on':['x']} ]})['reason'],'MISSING_DEPENDENCY')
 def test_cycle(self):self.assertEqual(validate_plan_graph({'steps':[{'id':'a','depends_on':['b']},{'id':'b','depends_on':['a']} ]})['reason'],'CYCLE')
 def test_valid(self):self.assertEqual(validate_plan_graph({'steps':[{'id':'a','depends_on':[]},{'id':'b','depends_on':['a']} ]})['status'],'PASS')
