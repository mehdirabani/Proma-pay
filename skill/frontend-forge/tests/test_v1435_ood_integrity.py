import unittest,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];B=ROOT/'benchmarks/ood_v2'
class T(unittest.TestCase):
 def test_ood_size_and_split(self):
  o=json.loads((B/'sealed_v2_public_tasks.json').read_text());c=json.loads((B/'calibration_tasks.json').read_text());self.assertGreaterEqual(len(o),100);self.assertGreaterEqual(len(c),200);self.assertTrue(set(x['id'] for x in o).isdisjoint(x['id'] for x in c))
 def test_ground_truth_not_imported_by_production(self):
  prod='\n'.join(p.read_text(errors='ignore') for d in ['retrieval','repository_intelligence','token_intelligence'] for p in (ROOT/d).glob('*.py'))
  self.assertNotIn('sealed_v2_ground_truth',prod);self.assertNotIn('ood_sealed_ground_truth',prod)
 def test_no_fixture_ids_special_cased(self):
  prod='\n'.join(p.read_text(errors='ignore') for d in ['retrieval','repository_intelligence'] for p in (ROOT/d).glob('*.py'))
  self.assertNotRegex(prod,r'sealed-\d|ood-\d|cal-\d')
