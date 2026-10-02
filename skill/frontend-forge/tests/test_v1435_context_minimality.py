import unittest
from retrieval.context_minimality import minimum_evidence_set
class T(unittest.TestCase):
 def test_prunes_redundant_consumers(self):
  c=[{'path':'A','reason':'target','graph_distance':0},{'path':'B','reason':'style','graph_distance':1},{'path':'C','reason':'style','graph_distance':1},{'path':'D','reason':'test','graph_distance':1}]
  r=minimum_evidence_set(c,['A']);self.assertIn('A',[x['path'] for x in r['selected']]);self.assertLess(len(r['selected']),len(c));self.assertGreater(r['zero_marginal_value_ratio'],0)
