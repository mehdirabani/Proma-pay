import unittest
from engineering_evidence.relevance import task_relevance
from engineering_evidence.independence import independence
class TestRelIndependent(unittest.TestCase):
 def test_unrelated_symbol_not_task_link(self):
  e={"claim_type":"SYMBOL_DEFINITION","observation":"Wrong component defines imports and symbols"}
  self.assertLess(task_relevance("fix checkout keyboard navigation",e)["score"],.45)
 def test_same_source_correlated(self):
  a={"evidence_id":"1","producer_id":"p","source_id":"parse","derivation_parent_ids":[]}
  b={"evidence_id":"2","producer_id":"q","source_id":"parse","derivation_parent_ids":[]}
  self.assertEqual(independence(a,b),"CORRELATED")
 def test_distinct_roots_independent(self):
  a={"evidence_id":"1","producer_id":"p","source_id":"symbol","derivation_parent_ids":[]}
  b={"evidence_id":"2","producer_id":"q","source_id":"graph","derivation_parent_ids":[]}
  self.assertEqual(independence(a,b),"INDEPENDENT")
