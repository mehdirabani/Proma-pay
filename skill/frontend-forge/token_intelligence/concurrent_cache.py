from pathlib import Path
import sqlite3,hashlib,time,json
from contextlib import contextmanager
class ConcurrentSummaryCache:
    def __init__(self,path,workspace_id='workspace'):
        self.path=Path(path);self.workspace_id=workspace_id;self.path.parent.mkdir(parents=True,exist_ok=True);self._init()
    @contextmanager
    def _con(self):
        c=sqlite3.connect(self.path,timeout=5)
        try:
            c.execute('PRAGMA journal_mode=WAL');c.execute('PRAGMA busy_timeout=5000')
            yield c
            c.commit()
        finally:
            c.close()
    def _init(self):
        with self._con() as c:c.execute('CREATE TABLE IF NOT EXISTS summaries(workspace_id TEXT,relative_path TEXT,content_hash TEXT,summary TEXT,version INTEGER,updated REAL,PRIMARY KEY(workspace_id,relative_path))')
    def put(self,relative_path,content,summary):
        h=hashlib.sha256(content.encode()).hexdigest()
        with self._con() as c:
            r=c.execute('SELECT version FROM summaries WHERE workspace_id=? AND relative_path=?',(self.workspace_id,relative_path)).fetchone();v=(r[0]+1 if r else 1)
            c.execute('INSERT INTO summaries VALUES(?,?,?,?,?,?) ON CONFLICT(workspace_id,relative_path) DO UPDATE SET content_hash=excluded.content_hash,summary=excluded.summary,version=excluded.version,updated=excluded.updated',(self.workspace_id,relative_path,h,summary,v,time.time()))
        return {'hash':h,'summary':summary,'version':v}
    def get(self,relative_path,content):
        h=hashlib.sha256(content.encode()).hexdigest()
        with self._con() as c:r=c.execute('SELECT content_hash,summary,version FROM summaries WHERE workspace_id=? AND relative_path=?',(self.workspace_id,relative_path)).fetchone()
        if not r or r[0]!=h:return None
        return {'hash':r[0],'summary':r[1],'version':r[2]}
