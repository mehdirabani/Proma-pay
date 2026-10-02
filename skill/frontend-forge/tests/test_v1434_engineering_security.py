import unittest,tempfile
from benchmarks.evaluation import EngineeringTaskEvaluator
class Eval(unittest.TestCase):
 def test_string_shell_command_rejected(self):
  with tempfile.TemporaryDirectory() as d:self.assertFalse(EngineeringTaskEvaluator().evaluate(d,{'build_cmd':'echo ok && echo bad'})['engineering_task_success'])
 def test_context_success_not_engineering_success(self):
  with tempfile.TemporaryDirectory() as d:
   r=EngineeringTaskEvaluator().evaluate(d,{'build_cmd':{'program':'node','args':['-e','process.exit(1)'],'timeout':3}});self.assertFalse(r['engineering_task_success'])
