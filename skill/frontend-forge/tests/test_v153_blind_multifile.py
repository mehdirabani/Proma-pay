import unittest
from benchmarks.engineering_v153_multifile.runner import run
class T(unittest.TestCase):
 def test_blind_multifile_pipeline(self):
  r=run();self.assertEqual(r['tasks'],50);self.assertEqual(r['solved'],50);self.assertEqual(r['unauthorized_patch_rate'],0)
  self.assertTrue(all(v=='ACCESS_DENIED' for v in r['sealed_access_probe'].values()))
  self.assertIn(r['isolation_mode'],{'SEPARATE_UID_PROCESS','LOCAL_PERMISSION_BOUNDARY'})
if __name__=='__main__':unittest.main()
