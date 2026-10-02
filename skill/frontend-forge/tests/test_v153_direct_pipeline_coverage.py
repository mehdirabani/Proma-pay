import unittest,tempfile,json,os
from pathlib import Path
from benchmarks.engineering_v153_multifile.execution_worker import execute_case
ROOT=Path(__file__).resolve().parents[1]
PUBLIC=json.loads((ROOT/'benchmarks/engineering_v153_multifile/public_tasks.json').read_text())
class T(unittest.TestCase):
 def test_one_per_causal_family_direct(self):
  chosen=[];seen=set()
  for c in PUBLIC:
   if c['cause_family'] not in seen:seen.add(c['cause_family']);chosen.append(c)
  with tempfile.TemporaryDirectory() as d:
   base=Path(d)/'work';base.mkdir();state=Path(d)/'.state';state.mkdir();os.environ['FFX_RUNTIME_STATE_HOME']=str(state)
   rows=[execute_case(c,base,str(state)) for c in chosen]
   self.assertEqual(len(rows),10);self.assertTrue(all(x['status']=='PATCH_APPLIED' for x in rows));self.assertTrue(all(x['patch']['change_receipt']['signature'] for x in rows))
if __name__=='__main__':unittest.main()
