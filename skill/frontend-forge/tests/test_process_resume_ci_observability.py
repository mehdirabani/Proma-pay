import unittest,tempfile,subprocess,sys,os,json
from pathlib import Path
from ci.ci_runner import run_ci
from observability.structured_log import log_record

ROOT=Path(__file__).resolve().parents[1]

class ProcessResumeCIObservabilityTests(unittest.TestCase):
    def test_resume_across_process_boundary(self):
        with tempfile.TemporaryDirectory() as d:
            db=str(Path(d,"db.sqlite"))
            code1=r"""
from persistence.sqlite_store import SQLiteStore
from runtime_py.runtime_v14 import Runtime
import sys
s=SQLiteStore(sys.argv[1]);r=Runtime("proc",store=s,context={});r.dispatch("TaskClassified");r.checkpoint("cp")
print(r.session.session_id)
"""
            env=dict(os.environ);env["PYTHONPATH"]=str(ROOT)
            p1=subprocess.run([sys.executable,"-c",code1,db],cwd=ROOT,env=env,capture_output=True,text=True,check=True)
            sid=p1.stdout.strip().splitlines()[-1]
            code2=r"""
from persistence.sqlite_store import SQLiteStore
from runtime_py.runtime_v14 import Runtime
import sys
s=SQLiteStore(sys.argv[1]);r=Runtime.resume(s,sys.argv[2]);print(r.state)
"""
            p2=subprocess.run([sys.executable,"-c",code2,db,sid],cwd=ROOT,env=env,capture_output=True,text=True,check=True)
            self.assertEqual(p2.stdout.strip().splitlines()[-1],"TASK_CLASSIFIED")
    def test_ci_empty_project_cannot_false_pass(self):
        with tempfile.TemporaryDirectory() as d:
            r=run_ci(d)
            self.assertNotEqual(r["status"],"PASS")
    def test_structured_log_redacts_secret(self):
        r=log_record("INFO","s","x","y",{"token":"ABCDEFGH123456"})
        self.assertNotIn("ABCDEFGH123456",json.dumps(r))
    def test_structured_log_has_audit_fields(self):
        r=log_record("INFO","s","x","y")
        for k in ["level","session_id","component","event","timestamp"]:self.assertIn(k,r)
