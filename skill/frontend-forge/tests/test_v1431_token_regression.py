import unittest
from token_intelligence.regression_gate import token_regression
from token_intelligence.token_report import accept_optimization
class T(unittest.TestCase):
 def test_regression(self):self.assertEqual(token_regression(100,125,90,90)['status'],'REGRESSION')
 def test_quality_loss_rejected(self):self.assertFalse(accept_optimization(100,50,90,60))
 def test_good_optimization(self):self.assertTrue(accept_optimization(100,70,90,91))
