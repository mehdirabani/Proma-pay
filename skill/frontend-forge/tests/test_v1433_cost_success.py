import unittest,tempfile
from pathlib import Path
from benchmarks.cost_accounting import CostLedger
from benchmarks.evaluation import context_success,EngineeringTaskEvaluator
class CostSuccess(unittest.TestCase):
 def test_context_and_engineering_are_separate(self):
  c=context_success(['a.ts'],{'relevant_files':['a.ts'],'critical_files':['a.ts']});self.assertTrue(c['context_success'])
  with tempfile.TemporaryDirectory() as d:
   Path(d,'x.txt').write_text('broken');e=EngineeringTaskEvaluator().evaluate(d,{'behavior_assertions':[{'path':'x.txt','contains':'fixed'}]});self.assertFalse(e['engineering_task_success'])
 def test_cost_units_not_mixed(self):
  x=CostLedger();x.add('prompt','context',100,'estimated_tokens','est');x.add('runtime','bytes',5000,'bytes','fs');t=x.totals();self.assertEqual(len(t),2)
  with self.assertRaises(ValueError):x.add('x','x',1,'tokens','bad')
