import unittest,time
from agents_runtime.agent_broker import AgentExecutionBroker
def good(task,ctx):return {"status":"PASS","answer":"ok"}
def bad(task,ctx):return {"x":1}
def slow(task,ctx):time.sleep(2);return {"status":"PASS","answer":"late"}
SCHEMA={"type":"object","required":["status","answer"],"properties":{"status":{"enum":["PASS"]},"answer":{"type":"string"}},"additionalProperties":False}
class TestAgentBroker(unittest.TestCase):
    def test_valid_output(self):self.assertEqual(AgentExecutionBroker({"a":good},{"a":SCHEMA}).execute("a","x",{},timeout=1)["status"],"PASS")
    def test_invalid_output(self):self.assertEqual(AgentExecutionBroker({"a":bad},{"a":SCHEMA}).execute("a","x",{},timeout=1)["status"],"INVALID_CAPABILITY_OUTPUT")
    def test_timeout(self):self.assertEqual(AgentExecutionBroker({"a":slow},{"a":SCHEMA}).execute("a","x",{},timeout=.2)["status"],"TOOL_TIMEOUT")
    def test_agent_receipt_signed(self):
        from provenance.verifier import verify_signed
        b=AgentExecutionBroker({'a':good},{'a':SCHEMA})
        try:r=b.execute('a','x',{},timeout=1,session_id='s',task_id='t')
        finally:b.close()
        self.assertTrue(verify_signed(r['execution_receipt']))
