import unittest
from benchmarks.claim_policy import claim_text
class Claims(unittest.TestCase):
 def test_estimated_not_llm_claim(self):
  s=claim_text('ESTIMATED_FIXTURE',84,'fixtures');self.assertIn('estimated context reduction',s);self.assertNotIn('LLM token reduction',s)
