import unittest
from ci.ci_runner import ci_outcome
class TestV142CI(unittest.TestCase):
    def test_fail_closed_matrix(self):
        cases={"PASS":"PASS","FAIL":"FAIL","TOOL_TIMEOUT":"FAIL","TOOL_UNAVAILABLE":"BLOCKED","SANDBOX_UNAVAILABLE":"BLOCKED",
               "SECURITY_BLOCKED":"FAIL","INVALID_CAPABILITY_OUTPUT":"FAIL","CANCELLED":"CANCELLED","UNKNOWN_STATUS":"FAIL"}
        for status,expected in cases.items():
            with self.subTest(status=status):self.assertEqual(ci_outcome(status,required=True),expected)
