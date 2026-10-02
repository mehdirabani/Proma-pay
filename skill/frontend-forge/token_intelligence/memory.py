from pathlib import Path
import json,time
TYPES={'session','task','project','architecture','component','decision','failure'}
class MemoryStore:
    def __init__(self,path):self.path=Path(path);self.items=self._load()
    def _load(self):
        try:return json.loads(self.path.read_text())
        except Exception:return []
    def add_candidate(self,memory_type,key,value,stable=False,reusable=False,validated=False,source_hash=None):
        if memory_type not in TYPES:raise ValueError('memory type')
        status='PROJECT_READY' if all([stable,reusable,validated]) else 'TRANSIENT'
        x={'type':memory_type,'key':key,'value':value,'stable':stable,'reusable':reusable,'validated':validated,'status':status,'source_hash':source_hash,'updated':time.time()};self.items=[i for i in self.items if not(i['type']==memory_type and i['key']==key)]+[x];self._save();return x
    def valid(self,memory_type,key,current_source_hash=None):
        for x in reversed(self.items):
            if x['type']==memory_type and x['key']==key:
                if current_source_hash is not None and x.get('source_hash') not in (None,current_source_hash):return None
                return x if x.get('validated') else None
        return None
    def prune(self,max_age=None):
        now=time.time();seen=set();kept=[]
        for x in sorted(self.items,key=lambda i:i['updated'],reverse=True):
            ident=(x['type'],x['key'])
            if ident in seen:continue
            if max_age and now-x['updated']>max_age:continue
            if x.get('status')=='CONTRADICTED':continue
            seen.add(ident);kept.append(x)
        self.items=list(reversed(kept));self._save();return len(self.items)
    def _save(self):self.path.parent.mkdir(parents=True,exist_ok=True);self.path.write_text(json.dumps(self.items,indent=2))
