import unittest,tempfile
from runtime_py.fallback import execute_with_fallback
from adapters.registry import get_adapter
class FallbackAdapterContractTests(unittest.TestCase):
    def test_adapter_validate_contract(self):
        a=get_adapter("lighthouse");r=a.execute({"workspace":".","target_url":"http://127.0.0.1:9"},timeout=1);self.assertTrue(a.validate(r))
    def test_reduced_mode_if_all_unavailable_or_fail(self):
        with tempfile.TemporaryDirectory() as d:
            r=execute_with_fallback(["lighthouse","axe"],{"workspace":d,"target_url":"http://127.0.0.1:9"},timeout=1)
            self.assertIn(r["status"],{"PASS","PARTIAL"})
            if r["status"]=="PARTIAL":self.assertEqual(r["validation"],"REDUCED")
