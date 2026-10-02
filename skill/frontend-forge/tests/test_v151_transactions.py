import unittest,tempfile
from pathlib import Path
from implementation.patch_engine import targeted_replace,apply_change_plan
class T(unittest.TestCase):
 def test_production_replace_transactional(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'a.txt').write_text('one');r=targeted_replace(d,'a.txt','one','two',mode='PRODUCTION');self.assertTrue(r['transactional']);self.assertEqual(Path(d,'a.txt').read_text(),'two')
 def test_multifile_crash_rolls_back(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'a').write_text('oldA');Path(d,'b').write_text('oldB');r=apply_change_plan(d,[{'operation':'MODIFY','path':'a','old':'oldA','new':'newA'},{'operation':'MODIFY','path':'b','old':'oldB','new':'newB'}],mode='PRODUCTION',crash_after=1)
   self.assertEqual(r['status'],'ROLLED_BACK');self.assertEqual(Path(d,'a').read_text(),'oldA');self.assertEqual(Path(d,'b').read_text(),'oldB')
