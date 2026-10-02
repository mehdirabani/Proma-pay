import unittest,tempfile,threading,time,sys,os,json
from pathlib import Path
from security.sandbox_policy import SandboxPolicy,sandbox_report
from security.process_supervisor import ProcessSupervisor,CancellationToken,PROCESS_REGISTRY
from adapters.registry import get_adapter
from browser.browser_runtime import BrowserRuntime
from security.path_policy import PathPolicyError

class V141SandboxTests(unittest.TestCase):
    def test_untrusted_production_refuses_without_full_backend_or_uses_full_backend(self):
        with tempfile.TemporaryDirectory() as d:
            outside=Path(d).parent/'ffx-outside-test.txt'
            if outside.exists():outside.unlink()
            Path(d,'package.json').write_text('{"scripts":{"build":"node evil.js"}}')
            Path(d,'evil.js').write_text(f"require('fs').writeFileSync({json.dumps(str(outside))},'x')")
            r=get_adapter('build').execute({'workspace':d,'script':'build','trust_level':'untrusted'},timeout=10)
            self.assertIn(r['status'],{'SANDBOX_UNAVAILABLE','PASS'})
            # If a full backend ran, outside write must still be impossible. If unavailable, script never ran.
            self.assertFalse(outside.exists(),r)
    def test_trusted_process_timeout_is_hard(self):
        with tempfile.TemporaryDirectory() as d:
            p=SandboxPolicy(d,trust_level='trusted',require_full_isolation=False,default_timeout=1,max_cpu_seconds=2)
            start=time.monotonic();r=ProcessSupervisor().run([sys.executable,'-c','import time;time.sleep(20)'],p,timeout=.4,mode='LOCAL')
            elapsed=time.monotonic()-start
            self.assertEqual(r['status'],'TOOL_TIMEOUT');self.assertLess(elapsed,3)
    def test_running_process_can_be_cancelled(self):
        with tempfile.TemporaryDirectory() as d:
            eid='cancel-me';p=SandboxPolicy(d,trust_level='trusted',require_full_isolation=False,default_timeout=20)
            holder={}
            def run():holder['r']=ProcessSupervisor().run([sys.executable,'-c','import time;time.sleep(20)'],p,timeout=20,execution_id=eid)
            t=threading.Thread(target=run);t.start()
            for _ in range(50):
                if eid in PROCESS_REGISTRY.running():break
                time.sleep(.02)
            self.assertTrue(PROCESS_REGISTRY.cancel(eid));t.join(3);self.assertFalse(t.is_alive());self.assertIn(holder['r']['status'],{'FAIL','CANCELLED'})
    def test_cancellation_token(self):
        with tempfile.TemporaryDirectory() as d:
            token=CancellationToken();p=SandboxPolicy(d,trust_level='trusted',require_full_isolation=False)
            holder={}
            t=threading.Thread(target=lambda:holder.setdefault('r',ProcessSupervisor().run([sys.executable,'-c','import time;time.sleep(20)'],p,timeout=20,cancellation_token=token)))
            t.start();time.sleep(.15);token.cancel();t.join(3);self.assertFalse(t.is_alive());self.assertEqual(holder['r']['status'],'CANCELLED')

    def test_file_size_resource_limit_applies(self):
        if os.name!='posix': self.skipTest('rlimit unix only')
        with tempfile.TemporaryDirectory() as d:
            p=SandboxPolicy(d,trust_level='trusted',require_full_isolation=False,max_file_size_mb=1,max_cpu_seconds=5)
            code="open('big.bin','wb').write(b'x'*(4*1024*1024))"
            r=ProcessSupervisor().run([sys.executable,'-c',code],p,timeout=5)
            self.assertNotEqual(r['status'],'PASS')
            self.assertLessEqual(Path(d,'big.bin').stat().st_size if Path(d,'big.bin').exists() else 0, 2*1024*1024)


    def test_timeout_leaves_no_registered_process(self):
        with tempfile.TemporaryDirectory() as d:
            eid='timeout-no-leak';p=SandboxPolicy(d,trust_level='trusted',require_full_isolation=False)
            r=ProcessSupervisor().run([sys.executable,'-c','import time;time.sleep(5)'],p,timeout=.2,execution_id=eid)
            self.assertEqual(r['status'],'TOOL_TIMEOUT');self.assertNotIn(eid,PROCESS_REGISTRY.running())

    def test_cancel_leaves_no_registered_process(self):
        with tempfile.TemporaryDirectory() as d:
            eid='cancel-no-leak';p=SandboxPolicy(d,trust_level='trusted',require_full_isolation=False);holder={}
            t=threading.Thread(target=lambda:holder.setdefault('r',ProcessSupervisor().run([sys.executable,'-c','import time;time.sleep(5)'],p,timeout=5,execution_id=eid)))
            t.start()
            for _ in range(50):
                if eid in PROCESS_REGISTRY.running():break
                time.sleep(.02)
            PROCESS_REGISTRY.cancel(eid);t.join(3)
            self.assertFalse(t.is_alive());self.assertNotIn(eid,PROCESS_REGISTRY.running());self.assertEqual(holder['r']['status'],'CANCELLED')

    def test_browser_screenshot_path_cannot_escape_even_when_browser_unavailable(self):
        with tempfile.TemporaryDirectory() as d:
            with self.assertRaises(PathPolicyError):BrowserRuntime(d).inspect('http://example.invalid',screenshot_path='../../outside.png')
