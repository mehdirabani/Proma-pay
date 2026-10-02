import unittest
from engineering_intelligence.task_analyzer import analyze_task
from engineering_intelligence.invariants import derive_invariants
from engineering_intelligence.risk_v2 import score_risk
class T(unittest.TestCase):
 def test_responsive_task(self):
  x=analyze_task('Fix mobile ProductCard overflow without changing desktop')
  self.assertEqual(x['task_type'],'RESPONSIVE_FIX');self.assertIn('responsive',x['domains']);self.assertIn('preserve_existing_behavior',x['constraints'])
 def test_persian_a11y(self):
  x=analyze_task('با کیبورد و تب نمی شود بین فیلدها حرکت کرد');self.assertEqual(x['task_type'],'ACCESSIBILITY_FIX')
 def test_invariants(self):self.assertIn('desktop behavior',derive_invariants(analyze_task('Fix mobile overflow'))['must_preserve'])
 def test_low_retrieval_confidence_increases_risk(self):self.assertGreater(score_risk(retrieval_state='TARGET_AMBIGUOUS')['score'],score_risk(retrieval_state='TARGET_CONFIRMED')['score'])
