import unittest,inspect
import benchmarks.engineering_v152_blind.execution as execution
class TestBlindBoundary(unittest.TestCase):
 def test_execution_source_has_no_sealed_access(self):
  src=inspect.getsource(execution)
  self.assertNotIn("sealed_evaluation",src);self.assertNotIn("expected_patch",src);self.assertNotIn("must_contain",src)
