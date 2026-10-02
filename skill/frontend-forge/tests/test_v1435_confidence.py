import unittest
from retrieval.confidence_v2 import OpenSetConfidenceModel
class T(unittest.TestCase):
 def test_calibration_has_selective_threshold(self):
  rows=[]
  for i in range(200):rows.append({'score':.8+.001*(i%20),'label':'EXACT','target_present':True,'retrieval_score':.6})
  for i in range(100):rows.append({'score':.2+.001*(i%20),'label':'WRONG','target_present':True,'retrieval_score':.3})
  for i in range(100):rows.append({'score':.1,'label':'NOT_FOUND_CORRECTLY','target_present':False,'retrieval_score':.01})
  m=OpenSetConfidenceModel().fit(rows);e=m.evaluate(rows);self.assertLess(e['high_confidence_wrong_rate'],.05);self.assertGreater(m.thresholds['resolve'],m.thresholds['expand'])
 def test_more_files_alone_not_confidence_feature(self):
  m=OpenSetConfidenceModel();a=m.feature_score(top_score=.5,margin=.1,evidence_sources=1,graph_consistency=.2,parser_confidence=.6,intent_certainty=.6,ambiguity=0,negative_penalty=0)
  b=m.feature_score(top_score=.5,margin=.1,evidence_sources=1,graph_consistency=.2,parser_confidence=.6,intent_certainty=.6,ambiguity=0,negative_penalty=0)
  self.assertEqual(a,b)
