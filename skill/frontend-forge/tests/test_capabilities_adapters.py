import unittest,tempfile,subprocess,os
from pathlib import Path
from runtime_py.capability_executor import execute_capability,CapabilityError
from adapters.registry import get_adapter
from runtime_py.environment import scan_environment

class CapabilityAdapterTests(unittest.TestCase):
    def test_builtin_capability_executes(self):
        r=execute_capability("requirement-reasoner",{"task":"build page"})
        self.assertEqual(r["status"],"PASS")
        self.assertIn("requirement_summary",r["result"])
    def test_unknown_capability_rejected(self):
        with self.assertRaises(CapabilityError): execute_capability("unknown-cap",{})
    def test_missing_input_rejected(self):
        with self.assertRaises(CapabilityError): execute_capability("requirement-reasoner",{})
    def test_git_adapter_real_or_unavailable(self):
        with tempfile.TemporaryDirectory() as d:
            subprocess.run(["git","init"],cwd=d,capture_output=True)
            r=get_adapter("git").execute({"workspace":d})
            self.assertIn(r["status"],{"PASS","TOOL_UNAVAILABLE","SANDBOX_UNAVAILABLE"})
    def test_typescript_adapter_real_or_unavailable(self):
        with tempfile.TemporaryDirectory() as d:
            Path(d,"tsconfig.json").write_text('{"compilerOptions":{"noEmit":true},"files":["a.ts"]}')
            Path(d,"a.ts").write_text("const x:number=1;")
            r=get_adapter("typescript").execute({"workspace":d},timeout=30)
            self.assertIn(r["status"],{"PASS","TOOL_UNAVAILABLE","SANDBOX_UNAVAILABLE"})
    def test_lighthouse_never_fakes(self):
        r=get_adapter("lighthouse").execute({"workspace":".","target_url":"http://127.0.0.1:9"},timeout=1)
        self.assertIn(r["status"],{"TOOL_UNAVAILABLE","FAIL","TOOL_TIMEOUT"})
        self.assertNotIn("score",r.get("evidence") or {})
    def test_environment_report_shape(self):
        r=scan_environment({})["environment_report"]
        self.assertIn("python",r);self.assertIn("tools",r)
    def test_unknown_adapter_rejected(self):
        with self.assertRaises(KeyError): get_adapter("nope")
