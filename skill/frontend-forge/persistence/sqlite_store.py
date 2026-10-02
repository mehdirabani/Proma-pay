import sqlite3,json,time,uuid
from contextlib import contextmanager
from pathlib import Path
from .integrity import mac,verify,hash_obj

class IntegrityFailure(RuntimeError): pass
class VersionConflict(RuntimeError): pass

class SQLiteStore:
    SCHEMA_VERSION=2
    def __init__(self,path):
        self.path=str(path);Path(path).parent.mkdir(parents=True,exist_ok=True);self._init()
    @contextmanager
    def con(self):
        c=sqlite3.connect(self.path,timeout=5)
        try:
            c.row_factory=sqlite3.Row;c.execute("PRAGMA foreign_keys=ON");c.execute("PRAGMA busy_timeout=5000");c.execute("PRAGMA journal_mode=WAL")
            yield c
            c.commit()
        finally:
            c.close()
    def _init(self):
        with self.con() as c:
            c.executescript("""
            CREATE TABLE IF NOT EXISTS meta(k TEXT PRIMARY KEY,v TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS sessions(session_id TEXT PRIMARY KEY,version INTEGER NOT NULL,data TEXT NOT NULL,signature TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY AUTOINCREMENT,session_id TEXT NOT NULL,sequence INTEGER NOT NULL,data TEXT NOT NULL,
              previous_hash TEXT NOT NULL,chain_hash TEXT NOT NULL,signature TEXT NOT NULL,UNIQUE(session_id,sequence),FOREIGN KEY(session_id) REFERENCES sessions(session_id));
            CREATE TABLE IF NOT EXISTS checkpoints(id INTEGER PRIMARY KEY AUTOINCREMENT,session_id TEXT NOT NULL,event_sequence INTEGER NOT NULL,
              session_version INTEGER NOT NULL,data TEXT NOT NULL,signature TEXT NOT NULL,created REAL NOT NULL,FOREIGN KEY(session_id) REFERENCES sessions(session_id));
            """)
            c.execute("INSERT OR REPLACE INTO meta(k,v) VALUES('schema_version',?)",(str(self.SCHEMA_VERSION),))
    def _signed_session(self,d,version):
        payload={"session_id":d["session_id"],"version":version,"data":d};return json.dumps(d,sort_keys=True),mac(payload)
    def create_session(self,d):
        body,sig=self._signed_session(d,1)
        with self.con() as c:c.execute("INSERT INTO sessions(session_id,version,data,signature) VALUES(?,?,?,?)",(d["session_id"],1,body,sig))
        return 1
    def get_session_record(self,sid):
        with self.con() as c:r=c.execute("SELECT version,data,signature FROM sessions WHERE session_id=?",(sid,)).fetchone()
        if not r:return None
        d=json.loads(r["data"]);payload={"session_id":sid,"version":r["version"],"data":d}
        if not verify(payload,r["signature"]):raise IntegrityFailure("STATE_INTEGRITY_FAILURE")
        return {"version":r["version"],"data":d}
    def get_session(self,sid):
        r=self.get_session_record(sid);return r["data"] if r else None
    def session_version(self,sid):
        r=self.get_session_record(sid);return r["version"] if r else None
    def update_session(self,d,expected_version=None):
        if expected_version is None:
            rec=self.get_session_record(d["session_id"])
            if not rec:raise KeyError(d["session_id"])
            expected_version=rec["version"]
        newv=expected_version+1;body,sig=self._signed_session(d,newv)
        with self.con() as c:
            cur=c.execute("UPDATE sessions SET version=?,data=?,signature=? WHERE session_id=? AND version=?",(newv,body,sig,d["session_id"],expected_version))
            if cur.rowcount!=1:raise VersionConflict("SESSION_VERSION_CONFLICT")
        return newv
    def append_event(self,sid,event):
        with self.con() as c:
            if not c.execute("SELECT 1 FROM sessions WHERE session_id=?",(sid,)).fetchone():raise KeyError(sid)
            last=c.execute("SELECT sequence,chain_hash FROM events WHERE session_id=? ORDER BY sequence DESC LIMIT 1",(sid,)).fetchone()
            seq=(last["sequence"]+1) if last else 1;prev=last["chain_hash"] if last else "GENESIS"
            stable={"session_id":sid,"sequence":seq,"event":event,"previous_hash":prev,"payload_hash":hash_obj(event)}
            chain_hash=hash_obj(stable);signed={**stable,"chain_hash":chain_hash};sig=mac(signed)
            c.execute("INSERT INTO events(session_id,sequence,data,previous_hash,chain_hash,signature) VALUES(?,?,?,?,?,?)",(sid,seq,json.dumps(event,sort_keys=True),prev,chain_hash,sig))
        return {**signed,"signature":sig}
    def list_event_envelopes(self,sid,verify_chain=True):
        with self.con() as c:rs=c.execute("SELECT sequence,data,previous_hash,chain_hash,signature FROM events WHERE session_id=? ORDER BY sequence",(sid,)).fetchall()
        out=[];prev="GENESIS"
        for r in rs:
            event=json.loads(r["data"]);stable={"session_id":sid,"sequence":r["sequence"],"event":event,"previous_hash":r["previous_hash"],"payload_hash":hash_obj(event)}
            expected_chain=hash_obj(stable);signed={**stable,"chain_hash":r["chain_hash"]}
            if verify_chain:
                if r["previous_hash"]!=prev or r["chain_hash"]!=expected_chain:raise IntegrityFailure("EVENT_CHAIN_FAILURE")
                if not verify(signed,r["signature"]):raise IntegrityFailure("EVENT_SIGNATURE_FAILURE")
            prev=r["chain_hash"];out.append({**signed,"signature":r["signature"]})
        return out
    def list_events(self,sid):return [x["event"] for x in self.list_event_envelopes(sid)]
    def derived_state(self,sid,initial="TASK_RECEIVED"):
        state=initial
        for e in self.list_events(sid):
            if e.get("from")!=state:raise IntegrityFailure("EVENT_STATE_SEQUENCE_FAILURE")
            state=e.get("to")
        return state
    def save_checkpoint(self,sid,label,session_data,trace):
        s=self.get_session_record(sid);events=self.list_event_envelopes(sid);seq=events[-1]["sequence"] if events else 0
        payload={"session_id":sid,"label":label,"event_sequence":seq,"session_version":s["version"],"session_data":session_data,"trace":trace}
        sig=mac(payload)
        with self.con() as c:
            cur=c.execute("INSERT INTO checkpoints(session_id,event_sequence,session_version,data,signature,created) VALUES(?,?,?,?,?,?)",(sid,seq,s["version"],json.dumps(payload,sort_keys=True),sig,time.time()));return cur.lastrowid
    def latest_checkpoint(self,sid):
        with self.con() as c:r=c.execute("SELECT id,data,signature,created FROM checkpoints WHERE session_id=? ORDER BY id DESC LIMIT 1",(sid,)).fetchone()
        if not r:return None
        payload=json.loads(r["data"])
        if not verify(payload,r["signature"]):raise IntegrityFailure("CHECKPOINT_INTEGRITY_FAILURE")
        # v14.1 compatibility keys
        return {"id":r["id"],"label":payload.get("label"),"session":payload.get("session_data"),"trace":payload.get("trace",[]),**payload,"created":r["created"]}
    def verify_resume(self,sid):
        rec=self.get_session_record(sid);derived=self.derived_state(sid);snapshot=rec["data"].get("state")
        if snapshot!=derived:raise IntegrityFailure("STATE_INTEGRITY_FAILURE")
        self.latest_checkpoint(sid);return {"status":"PASS","state":derived,"version":rec["version"]}
