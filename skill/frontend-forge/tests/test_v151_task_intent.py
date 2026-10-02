import unittest
from engineering_intelligence.task_analyzer import analyze_task
class T(unittest.TestCase):
 def test_implementation_not_repair(self):
  x=analyze_task('Implement responsive checkout page');self.assertEqual(x['operation'],'CREATE_FEATURE');self.assertIn('responsive',x['domains'])
 def test_accessible_feature_multilabel(self):
  x=analyze_task('Implement accessible checkout form');self.assertEqual(x['operation'],'CREATE_FEATURE');self.assertIn('accessibility',x['domains']);self.assertIn('form',x['domains'])
 def test_persian_feature(self):
  x=analyze_task('یک فرم پرداخت قابل دسترس بساز');self.assertEqual(x['operation'],'CREATE_FEATURE');self.assertIn('accessibility',x['domains'])
