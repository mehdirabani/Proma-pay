import unittest
from pathlib import Path
from runtime_py.io_utils import ROOT
class T(unittest.TestCase):
 def test_sealed_eval_not_referenced_by_production_code(self):
  hits=[]
  excluded={'benchmarks','tests','reports','.git'}
  for p in ROOT.rglob('*.py'):
   if any(x in p.relative_to(ROOT).parts for x in excluded):continue
   t=p.read_text(errors='ignore')
   if 'sealed_evaluation.json' in t or 'correct_patch_hash' in t:hits.append(str(p.relative_to(ROOT)))
  self.assertEqual(hits,[])
 def test_target_safety_required_by_router_docs(self):
  self.assertIn('Target Safety Gate',(ROOT/'SKILL.md').read_text())
