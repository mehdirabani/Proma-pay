from pathlib import Path
import hashlib,json,time
class FileSummaryCache:
    def __init__(self,path):self.path=Path(path);self.data=self._load()
    def _load(self):
        try:return json.loads(self.path.read_text())
        except Exception:return {}
    def _hash(self,p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()
    def get(self,p):
        p=Path(p).resolve();x=self.data.get(str(p))
        if not x:return None
        if x['hash']!=self._hash(p):return None
        return x
    def put(self,p,summary,exports=None,imports=None,responsibility='',dependencies=None,risk='unknown'):
        p=Path(p).resolve();self.data[str(p)]={'path':str(p),'hash':self._hash(p),'summary':summary,'exports':exports or [],'imports':imports or [],'responsibility':responsibility,'dependencies':dependencies or [],'risk':risk,'last_analyzed':time.time()};self._save();return self.data[str(p)]
    def _save(self):self.path.parent.mkdir(parents=True,exist_ok=True);self.path.write_text(json.dumps(self.data,indent=2))
    def prune(self):
        removed=[]
        for k in list(self.data):
            if not Path(k).exists():removed.append(k);self.data.pop(k)
        self._save();return removed
