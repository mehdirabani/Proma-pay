import unittest,tempfile,threading
from persistence.sqlite_store import SQLiteStore
class Concurrency(unittest.TestCase):
    def test_parallel_event_writes_same_session(self):
        with tempfile.TemporaryDirectory() as d:
            s=SQLiteStore(d+'/db');s.create_session({'session_id':'s','state':'TASK_RECEIVED'});errs=[]
            # Parallel writes may be serialized or explicitly fail with controlled DB conflict, but must not corrupt accepted events.
            def w(i):
                try:s.append_event('s',{'event':str(i),'from':'TASK_RECEIVED','to':'TASK_RECEIVED'})
                except Exception as e:errs.append(type(e).__name__)
            ts=[threading.Thread(target=w,args=(i,)) for i in range(20)];[t.start() for t in ts];[t.join() for t in ts]
            env=s.list_event_envelopes('s');self.assertEqual(len({e['sequence'] for e in env}),len(env));self.assertTrue(all(e['signature'] for e in env))
