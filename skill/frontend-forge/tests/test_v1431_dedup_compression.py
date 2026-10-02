import unittest
from token_intelligence.dedup import deduplicate
from token_intelligence.compression_engine import compress_typescript,compress_logs,compress_lighthouse
class T(unittest.TestCase):
 def test_duplicate_removed(self):self.assertEqual(len(deduplicate([{'text':'const x = 1;'},{'text':' const   x = 1; '}])['kept']),1)
 def test_ts_cluster(self):
  x='a.ts(1,1): error TS2322: bad\nb.ts(2,1): error TS2322: bad\n';r=compress_typescript(x);self.assertEqual(r[0]['count'],2)
 def test_logs(self):self.assertEqual(compress_logs('DEBUG x\nERROR bad\nINFO y'),['ERROR bad'])
 def test_lighthouse(self):self.assertIn('performance',compress_lighthouse({'categories':{'performance':{'score':.8}},'audits':{}})['scores'])
