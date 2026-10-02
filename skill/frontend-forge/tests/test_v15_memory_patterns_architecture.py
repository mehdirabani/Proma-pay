import unittest,tempfile
from pathlib import Path
from engineering_memory.store import EngineeringMemory
from project_patterns.miner import mine_patterns
from architecture_v15.smells import detect
class T(unittest.TestCase):
 def test_revision_aware_memory(self):
  with tempfile.TemporaryDirectory() as d:
   m=EngineeringMemory(Path(d)/'m.db');m.put('r1','root_cause','x',{'cause':'a'},True);self.assertIsNone(m.get('r2','root_cause','x'));self.assertTrue(m.get('r1','root_cause','x')['validated'])
 def test_pattern_mining(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'a.tsx').write_text("const x='a'; test('x',()=>{})")
   self.assertEqual(mine_patterns(d)['files_scanned'],1)
 def test_architecture_smell(self):self.assertTrue(detect({'Big.tsx':'\n'.join(['x']*501)}))
