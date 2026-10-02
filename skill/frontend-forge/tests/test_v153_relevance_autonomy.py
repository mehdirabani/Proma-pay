import unittest
from engineering_evidence.relevance_v3 import relevance
from engineering_evidence.independence_v3 import independence
from engineering_intelligence.autonomy_v3 import decide
class T(unittest.TestCase):
 def test_text_cannot_self_boost_without_structured_entities(self):
  raw={'claim_type':'TASK_TARGET_LINK','metadata':{'task_entities':[],'relationship':'NO_TASK_LINK'},'observation':'checkout checkout keyboard checkout'}
  self.assertLess(relevance({'task':'fix checkout keyboard'},raw)['score'],.2)
 def test_independence_unknown_and_partial(self):
  self.assertEqual(independence(None,{}),'UNKNOWN')
  a={'observation_root_id':'a','collector_id':'same','source_id':'1'};b={'observation_root_id':'b','collector_id':'same','source_id':'2'}
  self.assertEqual(independence(a,b),'PARTIALLY_CORRELATED')
 def test_same_observation_root_correlated(self):
  a={'observation_root_id':'o','collector_id':'a','source_id':'1'};b={'observation_root_id':'o','collector_id':'b','source_id':'2'}
  self.assertEqual(independence(a,b),'CORRELATED')
 def test_reproduced_low_risk_allows_l3_not_l4(self):
  r=decide({'operation':'REPAIR','risk':'LOW'},{'state':'TARGET_CONFIRMED'},{'status':'ROOT_CAUSE_CONFIRMED','causal_tier':'REPRODUCED'},{'risk':'LOW','centrality':0},.9,.9,True)
  self.assertEqual(r['level'],'L3_IMPLEMENT_LOW_RISK')
 def test_static_association_caps_l2(self):
  r=decide({'operation':'REPAIR','risk':'LOW'},{'state':'TARGET_CONFIRMED'},{'status':'ROOT_CAUSE_CONFIRMED','causal_tier':'STATIC_ASSOCIATION'},{'risk':'LOW','centrality':0},.9,.9,True)
  self.assertEqual(r['level'],'L2_PLAN')
 def test_high_impact_caps_l2(self):
  r=decide({'operation':'REPAIR','risk':'LOW'},{'state':'TARGET_CONFIRMED'},{'status':'ROOT_CAUSE_CONFIRMED','causal_tier':'REPRODUCED'},{'risk':'HIGH','centrality':.9},.9,.9,True)
  self.assertEqual(r['level'],'L2_PLAN')
if __name__=='__main__':unittest.main()
