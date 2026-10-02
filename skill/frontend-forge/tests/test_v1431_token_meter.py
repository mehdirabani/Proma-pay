import unittest
from token_intelligence.token_meter import DeterministicTokenEstimator,meter
class T(unittest.TestCase):
 def test_estimated_never_measured(self):self.assertEqual(DeterministicTokenEstimator().estimate('hello').status,'ESTIMATED')
 def test_meter_categories(self):self.assertIn('total_tokens',meter({'context':'abc','output':'def'}))
