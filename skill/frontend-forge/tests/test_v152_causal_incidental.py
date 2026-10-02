import unittest
from debugging.causal_reasoning import classify_role
class TestCausal(unittest.TestCase):
 def test_minwidth_incidental_for_color_bug(self):
  e={"observation":"fixed minimum width observed","metadata":{"domain":"responsive"},"task_relevance":{"score":.1}}
  self.assertIn(classify_role("button color is wrong",{"hypothesis":"min width causes overflow"},e),{"INCIDENTAL","INSUFFICIENT"})
 def test_minwidth_supports_overflow(self):
  e={"observation":"min-width 600px responsive overflow","metadata":{"domain":"responsive"},"task_relevance":{"score":.8}}
  self.assertEqual(classify_role("mobile overflow",{"hypothesis":"min width causes overflow"},e),"SUPPORTING")
