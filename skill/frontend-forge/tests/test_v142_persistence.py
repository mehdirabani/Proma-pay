import unittest,tempfile,sqlite3,json,threading
from persistence.sqlite_store import SQLiteStore,IntegrityFailure,VersionConflict

class TestV142Persistence(unittest.TestCase):
    def create(self,d,sid="s"):
        s=SQLiteStore(f"{d}/x.db");s.create_session({"session_id":sid,"state":"TASK_RECEIVED"});return s
    def test_session_row_tamper_detected(self):
        with tempfile.TemporaryDirectory() as d:
            s=self.create(d)
            with sqlite3.connect(s.path) as c:
                x=json.loads(c.execute("select data from sessions").fetchone()[0]);x["state"]="TASK_COMPLETED";c.execute("update sessions set data=?",(json.dumps(x),))
            with self.assertRaises(IntegrityFailure):s.get_session("s")
    def test_event_delete_detected_by_state_mismatch(self):
        with tempfile.TemporaryDirectory() as d:
            s=self.create(d);s.append_event("s",{"event":"x","from":"TASK_RECEIVED","to":"TASK_CLASSIFIED"})
            rec=s.get_session_record("s");data=rec["data"];data["state"]="TASK_CLASSIFIED";s.update_session(data,rec["version"])
            with sqlite3.connect(s.path) as c:c.execute("delete from events")
            with self.assertRaises(IntegrityFailure):s.verify_resume("s")
    def test_event_reorder_or_chain_tamper_detected(self):
        with tempfile.TemporaryDirectory() as d:
            s=self.create(d);s.append_event("s",{"event":"1","from":"TASK_RECEIVED","to":"TASK_CLASSIFIED"});s.append_event("s",{"event":"2","from":"TASK_CLASSIFIED","to":"CONTEXT_RESOLVED"})
            with sqlite3.connect(s.path) as c:c.execute("update events set previous_hash='bad' where sequence=2")
            with self.assertRaises(IntegrityFailure):s.list_event_envelopes("s")
    def test_checkpoint_tamper_detected(self):
        with tempfile.TemporaryDirectory() as d:
            s=self.create(d);s.save_checkpoint("s","x",{"state":"TASK_RECEIVED"},[])
            with sqlite3.connect(s.path) as c:c.execute("update checkpoints set data='{}'")
            with self.assertRaises(IntegrityFailure):s.latest_checkpoint("s")
    def test_optimistic_version_conflict(self):
        with tempfile.TemporaryDirectory() as d:
            s=self.create(d);rec=s.get_session_record("s");s.update_session(rec["data"],rec["version"])
            with self.assertRaises(VersionConflict):s.update_session(rec["data"],rec["version"])
    def test_20_parallel_sessions(self):
        with tempfile.TemporaryDirectory() as d:
            s=SQLiteStore(f"{d}/x.db");errs=[]
            def run(i):
                try:
                    sid=f"s{i}";s.create_session({"session_id":sid,"state":"TASK_RECEIVED"});s.append_event(sid,{"event":"x","from":"TASK_RECEIVED","to":"TASK_CLASSIFIED"})
                except Exception as e:errs.append(repr(e))
            ts=[threading.Thread(target=run,args=(i,)) for i in range(20)]
            [t.start() for t in ts];[t.join() for t in ts]
            self.assertEqual(errs,[])
            for i in range(20):self.assertEqual(len(s.list_events(f"s{i}")),1)
