from pathlib import Path
import tempfile,shutil,os,hashlib
class AtomicFileTransaction:
    def __init__(self,workspace):
        self.root=Path(workspace).resolve();self.backups={};self.created=[];self.committed=False
    def _path(self,rel):
        p=(self.root/rel).resolve()
        p.relative_to(self.root)
        return p
    def write(self,rel,data):
        p=self._path(rel);p.parent.mkdir(parents=True,exist_ok=True)
        if p.exists() and rel not in self.backups:self.backups[rel]=p.read_bytes()
        if not p.exists() and rel not in self.created:self.created.append(rel)
        tmp=p.with_name(p.name+".ffx.tmp");tmp.write_bytes(data if isinstance(data,bytes) else str(data).encode())
        os.replace(tmp,p)
    def commit(self):self.committed=True
    def rollback(self):
        for rel,b in self.backups.items():self._path(rel).write_bytes(b)
        for rel in self.created:
            p=self._path(rel)
            if p.exists():p.unlink()
        self.committed=False
    def __enter__(self):return self
    def __exit__(self,typ,val,tb):
        if typ or not self.committed:self.rollback()
