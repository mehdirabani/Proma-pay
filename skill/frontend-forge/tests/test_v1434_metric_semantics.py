import unittest
from benchmarks.metric_contract import metric
class M(unittest.TestCase):
 def test_units_required(self):
  self.assertEqual(metric('bytes_read',10,'bytes','fs')['unit'],'bytes')
  with self.assertRaises(ValueError):metric('x',1,'tokens','bad')
