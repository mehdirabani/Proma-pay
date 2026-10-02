import unittest,tempfile,time,threading,sys
from pathlib import Path
from security.process_supervisor import ProcessSupervisor,PROCESS_REGISTRY
from security.sandbox_policy import SandboxPolicy
from security.package_manager_policy import execution_plan
from regression.baseline_store import BaselineStore
from provenance.execution_broker import ExecutionBroker

class AdditionalSecurity(unittest.TestCase):
    def test_session_wide_cancel(self):
        with tempfile.TemporaryDirectory() as d:
            sup=ProcessSupervisor(); result={}
            def run():result.update(sup.run([sys.executable,'-c','import time;time.sleep(10)'],SandboxPolicy(d,trust_level='PROJECT_TRUSTED',require_full_isolation=False),timeout=20,session_id='sess',capability_id='x'))
            t=threading.Thread(target=run);t.start();time.sleep(.3);cancelled=PROCESS_REGISTRY.cancel_session('sess');t.join(3)
            self.assertTrue(cancelled);self.assertEqual(result.get('status'),'CANCELLED')
    def test_malicious_lifecycle_is_visible(self):
        root=Path(__file__).resolve().parents[1]/'fixtures/malicious-project';plan=execution_plan(root,'build')
        self.assertEqual([x['name'] for x in plan['commands']],['prebuild','build','postbuild'])
    def test_baseline_rejects_fake_derived_dict(self):
        with tempfile.TemporaryDirectory() as d:
            b=ExecutionBroker()
            try:
                store=BaselineStore(Path(d)/'base',authority=b._authority)
                with self.assertRaises(ValueError):store.put_production('x',{'performance':90},project_revision='r',workspace=d,evidence_refs=[{'provenance':'derived'}])
            finally:b.close()
