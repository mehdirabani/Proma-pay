import unittest,tempfile
from pathlib import Path
from runtime_py.scheduler import dependency_levels,execute_schedule
from failure.retry import run_with_retry
from visual.screenshot_compare import compare_images
from agents_runtime.local_agent_host import LocalAgentHost
from agents_runtime.output_validation import validate_agent_output
import jsonschema

class MiscRuntimeTests(unittest.TestCase):
    def test_scheduler_levels(self):
        self.assertEqual(dependency_levels(["quality-engine","regression-runner"])[-1],["regression-runner"])
    def test_scheduler_cycle_detected(self):
        with self.assertRaises(ValueError):dependency_levels(["a","b"],{"a":["b"],"b":["a"]})
    def test_parallel_environment_and_requirement(self):
        ctx={"environment-scan":{},"requirement-reasoner":{"task":"x"}}
        r=execute_schedule(["environment-scan","requirement-reasoner"],ctx,max_workers=2);self.assertEqual(set(r),set(ctx))
    def test_retry_succeeds(self):
        n={"x":0}
        def f():
            n["x"]+=1
            if n["x"]<2:raise ValueError("x")
            return 1
        r=run_with_retry(f,2);self.assertEqual(r["status"],"PASS");self.assertEqual(r["attempts"],2)
    def test_retry_bounded(self):
        r=run_with_retry(lambda:(_ for _ in ()).throw(ValueError("x")),2);self.assertEqual(r["status"],"FAIL");self.assertEqual(r["attempts"],2)
    def test_visual_same(self):
        try:
            from PIL import Image
        except Exception:self.skipTest("Pillow unavailable")
        with tempfile.TemporaryDirectory() as d:
            a=Path(d,"a.png");b=Path(d,"b.png");Image.new("RGB",(4,4),"white").save(a);Image.new("RGB",(4,4),"white").save(b)
            self.assertEqual(compare_images(a,b)["status"],"PASS")
    def test_visual_diff(self):
        try:
            from PIL import Image
        except Exception:self.skipTest("Pillow unavailable")
        with tempfile.TemporaryDirectory() as d:
            a=Path(d,"a.png");b=Path(d,"b.png");Image.new("RGB",(4,4),"white").save(a);Image.new("RGB",(4,4),"black").save(b)
            self.assertEqual(compare_images(a,b,0.01)["status"],"FAIL")
    def test_agent_host(self):
        h=LocalAgentHost({"a":lambda task,ctx:{"status":"PASS","artifacts":[],"summary":task}})
        out=h.execute("a","hello",{});self.assertEqual(out["summary"],"hello")
    def test_agent_output_valid(self):self.assertTrue(validate_agent_output({"status":"PASS","artifacts":[],"summary":"ok"}))
    def test_agent_output_invalid(self):
        with self.assertRaises(jsonschema.ValidationError):validate_agent_output({"status":"PASS"})
