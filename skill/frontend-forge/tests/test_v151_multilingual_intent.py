import unittest,json
from runtime_py.io_utils import ROOT
from engineering_intelligence.task_analyzer import analyze_task
class T(unittest.TestCase):
    def test_multilingual_intent_set(self):
        rows=json.loads((ROOT/'benchmarks/engineering_v151/multilingual_intent.json').read_text())
        for row in rows:
            out=analyze_task(row['task'])
            with self.subTest(task=row['task']):
                self.assertEqual(out['operation'],row['operation'])
                self.assertTrue(set(row['domains']).issubset(set(out['domains'])))
