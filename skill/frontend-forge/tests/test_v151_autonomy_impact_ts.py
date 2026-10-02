import unittest,tempfile
from pathlib import Path
from engineering_intelligence.autonomy import choose_autonomy
from impact.impact_predictor import predict
from code_intelligence.type_intelligence import analyze_file
class T(unittest.TestCase):
 def test_unverified_root_caps_at_l2(self):
  a=choose_autonomy({'task_type':'BUG_FIX','risk':'LOW'},{'state':'TARGET_CONFIRMED'},root_cause={'status':'ROOT_CAUSE_UNVERIFIED'},code_intelligence_mode='FULL_COMPILER');self.assertEqual(a['level'],'L2_PLAN')
 def test_reduced_high_risk_caps_at_l2(self):
  a=choose_autonomy({'task_type':'REFACTOR','risk':'HIGH'},{'state':'TARGET_CONFIRMED'},root_cause={'status':'ROOT_CAUSE_CONFIRMED'},code_intelligence_mode='REDUCED_REGEX');self.assertEqual(a['level'],'L2_PLAN')
 def test_centrality_derived(self):
  g={'A':{'consumers':['B','C']},'B':{},'C':{}};r=predict(['A'],g);self.assertGreater(r['centrality'],0)
 def test_ts_compiler_mode_real_or_reduced(self):
  with tempfile.TemporaryDirectory() as d:
   p=Path(d,'a.ts');p.write_text('interface A {x:number}; const f=(a:A)=>a.x; f({x:1})');r=analyze_file(p);self.assertIn(r['mode'],{'FULL_COMPILER','REDUCED_REGEX'})
   if r['mode']=='FULL_COMPILER':self.assertTrue(any(x['name']=='A' for x in r['symbols']))
