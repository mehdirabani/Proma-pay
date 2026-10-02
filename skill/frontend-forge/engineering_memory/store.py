import sqlite3,json,time
from pathlib import Path
class EngineeringMemory:
    def __init__(self,path):
        self.path=str(path);Path(path).parent.mkdir(parents=True,exist_ok=True)
        with sqlite3.connect(self.path) as c:c.execute('CREATE TABLE IF NOT EXISTS memory(id INTEGER PRIMARY KEY, revision TEXT, kind TEXT, key TEXT, data TEXT, validated INTEGER, created REAL)')
    def put(self,revision,kind,key,data,validated=False):
        with sqlite3.connect(self.path) as c:c.execute('INSERT INTO memory(revision,kind,key,data,validated,created) VALUES(?,?,?,?,?,?)',(revision,kind,key,json.dumps(data),1 if validated else 0,time.time()))
    def get(self,revision,kind,key):
        with sqlite3.connect(self.path) as c:r=c.execute('SELECT data,validated FROM memory WHERE revision=? AND kind=? AND key=? ORDER BY id DESC LIMIT 1',(revision,kind,key)).fetchone()
        return {'data':json.loads(r[0]),'validated':bool(r[1])} if r else None
