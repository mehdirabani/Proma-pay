import unittest
from token_intelligence.agent_cost import choose_agents,handoff
class T(unittest.TestCase):
 def test_trivial_one_agent(self):
  c=[{'name':'a','expected_quality_gain':3,'expected_token_cost':100},{'name':'b','expected_quality_gain':2,'expected_token_cost':10}]
  self.assertEqual(len(choose_agents(c,'TRIVIAL')),1)
 def test_handoff_compact(self):self.assertEqual(set(handoff('d',['a'],['f'],['q'])),{'decision','artifact_references','critical_findings','open_questions'})
