import unittest
from retrieval.confidence import EmpiricalConfidenceCalibrator
class Confidence(unittest.TestCase):
 def test_score_not_confidence(self):
  c=EmpiricalConfidenceCalibrator().fit([(0.1,0),(0.2,0),(0.7,1),(0.8,1)]);self.assertNotEqual(c.calibrate(.7),.7)
 def test_metrics(self):
  c=EmpiricalConfidenceCalibrator().fit([(0.1,0),(0.2,0),(0.7,1),(0.8,1)]);r=c.evaluate([(0.15,0),(0.75,1)]);self.assertIn('brier',r);self.assertIn('ece',r)
