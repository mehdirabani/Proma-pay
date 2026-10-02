import unittest,time,threading
from security.process_supervisor import CancellationToken
from runtime_py.capability_executor import execute_capability

class V141CapabilityContractTests(unittest.TestCase):
    def test_invalid_engine_output_is_rejected(self):
        r=execute_capability('test-invalid-output',{})
        self.assertEqual(r['status'],'INVALID_CAPABILITY_OUTPUT')
    def test_engine_timeout_really_returns_quickly(self):
        start=time.monotonic();r=execute_capability('test-sleep-engine',{'seconds':20});elapsed=time.monotonic()-start
        self.assertEqual(r['status'],'TOOL_TIMEOUT');self.assertLess(elapsed,4)

    def test_internal_capability_cancellation_kills_worker(self):
        token=CancellationToken();holder={}
        t=threading.Thread(target=lambda:holder.setdefault('r',execute_capability('test-sleep-engine',{'seconds':20,'cancellation_token':token})))
        t.start();time.sleep(.2);token.cancel();t.join(4)
        self.assertFalse(t.is_alive());self.assertEqual(holder['r']['status'],'CANCELLED')

    def test_valid_engine_output_contract(self):
        r=execute_capability('requirement-reasoner',{'task':'build page'});self.assertEqual(r['status'],'PASS');self.assertIn('requirement_summary',r['result'])
