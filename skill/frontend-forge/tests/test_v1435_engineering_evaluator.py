import unittest,tempfile
from benchmarks.evaluation import EngineeringTaskEvaluator
class T(unittest.TestCase):
 def test_shell_string_rejected(self):
  with tempfile.TemporaryDirectory() as d:
   r=EngineeringTaskEvaluator().evaluate(d,{'build_cmd':'echo pwned && true'});self.assertFalse(r['engineering_task_success']);self.assertEqual(r['checks']['build']['status'],'INVALID_COMMAND_CONTRACT')
