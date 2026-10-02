import unittest,tempfile,sys,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'benchmarks/token-efficiency'))
from benchmark_runner import main
class T(unittest.TestCase):
 def test_report_is_estimated_and_reproducible_shape(self):
  with tempfile.TemporaryDirectory() as d:
   r=main(d);self.assertEqual(r['measurement'],'ESTIMATED_TOKEN_USAGE');self.assertGreater(len(r['runs']),10);self.assertTrue(Path(d,'BREAK_EVEN_REPORT.json').exists())
 def test_no_accepted_quality_regression(self):
  with tempfile.TemporaryDirectory() as d:
   r=main(d);self.assertTrue(all(x['quality_delta']>=0 for x in r['runs'] if x['accepted']))
