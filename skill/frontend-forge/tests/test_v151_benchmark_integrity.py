import unittest,json,tempfile,shutil
from pathlib import Path
from runtime_py.io_utils import ROOT
class T(unittest.TestCase):
 def test_50_unique_tasks(self):
  pub=json.loads((ROOT/'benchmarks/engineering_v151/public_tasks.json').read_text());self.assertGreaterEqual(len(pub),50);self.assertEqual(len({x['fixture'] for x in pub}),len(pub));self.assertGreaterEqual(len({x['category'] for x in pub}),10)
 def test_public_has_no_hidden_truth(self):
  pub=json.loads((ROOT/'benchmarks/engineering_v151/public_tasks.json').read_text());forbidden={'target','old','new','hidden_assertions','correct_patch'};self.assertFalse(any(forbidden & set(x) for x in pub))
 def test_sealed_not_imported_by_production(self):
  hits=[]
  for p in ROOT.rglob('*.py'):
   rel=p.relative_to(ROOT)
   if rel.parts[0] in {'benchmarks','tests'}:continue
   t=p.read_text(errors='ignore')
   if 'engineering_v151/sealed_evaluation.json' in t:hits.append(str(rel))
  self.assertEqual(hits,[])
