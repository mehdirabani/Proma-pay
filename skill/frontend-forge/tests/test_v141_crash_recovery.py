import unittest,tempfile,subprocess,sys,os
from pathlib import Path
from persistence.sqlite_store import SQLiteStore
from runtime_py.runtime_v14 import Runtime
ROOT=Path(__file__).resolve().parents[1]
class CrashRecoveryTests(unittest.TestCase):
    def test_hard_process_exit_after_checkpoint_can_resume(self):
        with tempfile.TemporaryDirectory() as d:
            db=Path(d,'db.sqlite');sidfile=Path(d,'sid')
            code="""
from pathlib import Path
import os,sys
from persistence.sqlite_store import SQLiteStore
from runtime_py.runtime_v14 import Runtime
s=SQLiteStore(sys.argv[1]);r=Runtime('crash',store=s,context={});r.dispatch('TaskClassified');r.checkpoint('last-safe');Path(sys.argv[2]).write_text(r.session.session_id);os._exit(137)
"""
            env=dict(os.environ);env['PYTHONPATH']=str(ROOT)
            p=subprocess.run([sys.executable,'-c',code,str(db),str(sidfile)],cwd=ROOT,env=env)
            self.assertEqual(p.returncode,137)
            sid=sidfile.read_text();store=SQLiteStore(db);r=Runtime.resume(store,sid);self.assertEqual(r.state,'TASK_CLASSIFIED');self.assertIsNotNone(store.latest_checkpoint(sid))
