from pathlib import Path
import sqlite3,json,time
from contextlib import contextmanager
class GraphStore:
    def __init__(self,path):self.path=Path(path);self.path.parent.mkdir(parents=True,exist_ok=True);self._init()
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
        with self._con() as c:c.execute('CREATE TABLE IF NOT EXISTS graph(id INTEGER PRIMARY KEY CHECK(id=1),version INTEGER,data TEXT,updated REAL)')
    def save(self,index):
        with self._con() as c:
            row=c.execute('SELECT version FROM graph WHERE id=1').fetchone();v=(row[0]+1) if row else 1
            c.execute('INSERT INTO graph(id,version,data,updated) VALUES(1,?,?,?) ON CONFLICT(id) DO UPDATE SET version=excluded.version,data=excluded.data,updated=excluded.updated',(v,json.dumps(index),time.time()))
        return v
    def load(self):
        with self._con() as c:r=c.execute('SELECT version,data FROM graph WHERE id=1').fetchone()
        return None if not r else {'store_version':r[0],'index':json.loads(r[1])}
