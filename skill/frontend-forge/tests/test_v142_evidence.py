import unittest,tempfile,json,os,stat
from pathlib import Path
from provenance.execution_broker import ExecutionBroker
from security.sandbox_policy import SandboxPolicy
from evidence.evidence_store import create_tool_evidence,validate_evidence,create_evidence

class TestV142Evidence(unittest.TestCase):
    def make(self,d,sid='s1',rev='r1'):
        data=json.dumps({'categories':{'performance':{'score':.91},'accessibility':{'score':.93},'seo':{'score':.95}}})
        tool=Path(d)/'lighthouse';tool.write_text('#!/bin/sh\nprintf \'%s\\n\' '+repr(data)+'\n');tool.chmod(tool.stat().st_mode|stat.S_IXUSR)
        old=os.environ.get('PATH','');os.environ['PATH']=d+os.pathsep+old
        try:
            b=ExecutionBroker()
            r=b.execute(session_id=sid,task_id='t1',capability_id='lighthouse-check',adapter_id='lighthouse',actor='lighthouse',argv=['lighthouse'],
                policy=SandboxPolicy(d,trust_level='PROJECT_TRUSTED',require_full_isolation=False),runtime_mode='LOCAL',project_revision=rev,timeout=5)
            self.assertEqual(r['status'],'PASS',r)
            p=Path(d)/'lh.json';p.write_text(r['stdout'])
            a=b.attest_artifact(r['execution_receipt'],p,source_kind='stdout')
            e=create_tool_evidence('performance','lighthouse',r['execution_receipt'].get('tool_version') or 'x',p,d,a,r['execution_receipt'],sid,'t1',rev,'lighthouse-check')
            b.close();return p,e
        finally:os.environ['PATH']=old
    def test_derived_evidence_valid(self):
        with tempfile.TemporaryDirectory() as d:
            p,e=self.make(d);self.assertTrue(validate_evidence(e,'PRODUCTION'))
    def test_score_tamper_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            p,e=self.make(d);e['score']=100;self.assertFalse(validate_evidence(e,'PRODUCTION'))
    def test_cross_session_evidence_replay_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            p,e=self.make(d);self.assertFalse(validate_evidence(e,'PRODUCTION',session_id='s2'))
    def test_cross_revision_evidence_replay_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            p,e=self.make(d);self.assertFalse(validate_evidence(e,'PRODUCTION',project_revision='other'))
    def test_synthetic_production_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            p=Path(d)/'x';p.write_text('x');e=create_evidence('performance',100,'lighthouse','x',p)
            self.assertFalse(validate_evidence(e,'PRODUCTION'));self.assertTrue(validate_evidence(e,'SIMULATION'))
