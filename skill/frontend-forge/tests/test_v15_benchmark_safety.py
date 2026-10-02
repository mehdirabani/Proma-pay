import unittest,json
from pathlib import Path
from benchmarks.engineering_v15.evaluator import validate_command
from runtime_py.io_utils import ROOT
class T(unittest.TestCase):
 def test_50_plus_tasks_and_splits(self):
  x=json.loads((ROOT/'benchmarks/engineering_v15/public_tasks.json').read_text());self.assertGreaterEqual(len(x),50);self.assertEqual({i['split'] for i in x},{'development','validation','sealed_evaluation'})
 def test_public_tasks_no_hidden_fields(self):
  x=json.loads((ROOT/'benchmarks/engineering_v15/public_tasks.json').read_text());self.assertFalse(any('expected_files' in i or 'correct_patch' in i or 'hidden' in i for i in x))
 def test_shell_string_rejected(self):
  with self.assertRaises(ValueError):validate_command('npm test && rm x')
 def test_structured_command(self):self.assertTrue(validate_command({'program':'npm','args':['test'],'timeout':10}))
