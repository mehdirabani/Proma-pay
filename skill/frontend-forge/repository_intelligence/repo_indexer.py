from pathlib import Path
import hashlib,time,re
from .dependency_extractor import parse_file
from .tsconfig_resolver import ProjectResolver
EXCLUDE_PARTS={'.git','node_modules','dist','build','coverage','.next','vendor','__pycache__'}
LOCKS={'package-lock.json','pnpm-lock.yaml','yarn.lock'}
CODE_EXT={'.ts','.tsx','.js','.jsx','.mjs','.css','.scss','.html'}
def file_hash(p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()
class RepositoryIndexer:
    SCHEMA_VERSION=3
    def __init__(self,root):self.root=Path(root).resolve();self.resolver=ProjectResolver(self.root)
    def scan(self):
        t0=time.perf_counter();nodes={};parser_modes=set();files_opened=0;bytes_read=0;files_stated=0
        paths=[]
        for p in self.root.rglob('*'):
            if not p.is_file() or any(x in EXCLUDE_PARTS for x in p.parts):continue
            files_stated+=1
            if p.name in LOCKS or p.suffix.lower() not in CODE_EXT:continue
            paths.append(p)
        for p in paths:
            rel=str(p.relative_to(self.root)).replace('\\','/');raw=p.read_bytes();files_opened+=1;bytes_read+=len(raw)
            parsed=parse_file(p);parser_modes.add(parsed['mode']);imports=[];reexports=[]
            for spec in parsed.get('imports',[]):imports.append(self.resolver.resolve(p,spec) or spec)
            for spec in parsed.get('reexports',[]):reexports.append(self.resolver.resolve(p,spec) or spec)
            nodes[rel]={'path':rel,'hash':hashlib.sha256(raw).hexdigest(),'language':p.suffix.lower(),'symbols':parsed.get('symbols',[]),
              'exports':parsed.get('exports',[]),'imports':imports,'reexports':reexports,'jsx_components':parsed.get('jsx_components',[]),
              'string_literals':parsed.get('string_literals',[]),'aria_labels':parsed.get('aria_labels',[]),'test_descriptions':parsed.get('test_descriptions',[]),'updated_at':time.time()}
        reverse={k:[] for k in nodes}
        for src,n in nodes.items():
            for dep in n['imports']+n['reexports']:
                if dep in reverse:reverse[dep].append(src)
        for k in nodes:nodes[k]['reverse_imports']=sorted(set(reverse[k]))
        indexes=self._indexes(nodes);self._relationships(nodes,indexes)
        elapsed=(time.perf_counter()-t0)*1000
        return {'version':self.SCHEMA_VERSION,'workspace_id':self._workspace_id(),'parser_mode':'REDUCED' if 'REDUCED' in parser_modes else 'PARTIAL',
          'resolver_mode':self.resolver.mode,'nodes':nodes,'indexes':indexes,'workspaces':self.resolver.workspaces,'created_at':time.time(),
          'io_metrics':{'files_stat_ed':files_stated,'files_opened':files_opened,'bytes_read':bytes_read,'index_time_ms':round(elapsed,3)}}
    def _workspace_id(self):
        pkg=self.root/'package.json';identity=(pkg.read_text(errors='ignore') if pkg.exists() else self.root.name)
        return hashlib.sha256(identity.encode()).hexdigest()[:20]
    def _indexes(self,nodes):
        idx={'stem':{},'symbol':{},'export':{},'string':{},'route':{},'test_stem':{},'style_stem':{},'token':{}}
        def add(kind,key,val):idx[kind].setdefault(key.lower(),[]).append(val)
        for rel,n in nodes.items():
            stem=Path(rel).stem.replace('.test','').replace('.spec','');add('stem',stem,rel)
            for x in n.get('symbols',[]):add('symbol',x,rel)
            for x in n.get('exports',[]):add('export',x,rel)
            for x in n.get('string_literals',[])+n.get('aria_labels',[])+n.get('test_descriptions',[]):
                for token in re.findall(r'[\w\u0600-\u06ff-]+',x.lower()):
                    if len(token)>2:add('string',token,rel)
            token_text=' '.join([rel,*n.get('symbols',[]),*n.get('exports',[]),*n.get('jsx_components',[]),*n.get('string_literals',[]),*n.get('aria_labels',[]),*n.get('test_descriptions',[])])
            for token in re.findall(r'[a-z0-9\u0600-\u06ff]+',token_text.lower()):
                if len(token)>1:add('token',token,rel)
            if '/pages/' in '/'+rel or '/app/' in '/'+rel:add('route',stem,rel)
            if 'test' in rel.lower() or 'spec' in rel.lower():add('test_stem',stem,rel)
            if Path(rel).suffix in {'.css','.scss'}:add('style_stem',stem,rel)
        for kind in idx:
            for k in idx[kind]:idx[kind][k]=sorted(set(idx[kind][k]))
        return idx
    def _relationships(self,nodes,idx):
        for rel,n in nodes.items():
            stem=Path(rel).stem.replace('.test','').replace('.spec','').lower()
            tests=set(idx['test_stem'].get(stem,[]));styles=set(idx['style_stem'].get(stem,[]))
            # fallback normalized stem containment only within indexed stems, not all files
            if not tests:
                for k,v in idx['test_stem'].items():
                    if stem in k or k in stem:tests.update(v)
            if not styles:
                for k,v in idx['style_stem'].items():
                    if stem==k or stem.replace('card','')==k:styles.update(v)
            n['tests']=sorted(tests);n['styles']=sorted(styles);n['routes']=[rel] if rel in sum(idx['route'].values(),[]) else []
    def update(self,index,changed_paths):
        # Safe incremental API: rebuild only changed nodes, then O(E) reverse/index relationships.
        nodes=dict(index.get('nodes',{}));changed=[]
        for rel in changed_paths:
            rel=str(rel).replace('\\','/');p=self.root/rel
            if not p.exists():nodes.pop(rel,None);changed.append(rel);continue
            if p.suffix.lower() not in CODE_EXT:continue
            raw=p.read_bytes();h=hashlib.sha256(raw).hexdigest()
            if nodes.get(rel,{}).get('hash')==h:continue
            parsed=parse_file(p);imports=[self.resolver.resolve(p,s) or s for s in parsed.get('imports',[])];reexports=[self.resolver.resolve(p,s) or s for s in parsed.get('reexports',[])]
            nodes[rel]={'path':rel,'hash':h,'language':p.suffix.lower(),'symbols':parsed.get('symbols',[]),'exports':parsed.get('exports',[]),'imports':imports,'reexports':reexports,
              'jsx_components':parsed.get('jsx_components',[]),'string_literals':parsed.get('string_literals',[]),'aria_labels':parsed.get('aria_labels',[]),'test_descriptions':parsed.get('test_descriptions',[]),'updated_at':time.time()};changed.append(rel)
        reverse={k:[] for k in nodes}
        for src,n in nodes.items():
            for dep in n.get('imports',[])+n.get('reexports',[]):
                if dep in reverse:reverse[dep].append(src)
        for k in nodes:nodes[k]['reverse_imports']=sorted(set(reverse[k]))
        indexes=self._indexes(nodes);self._relationships(nodes,indexes)
        return {**index,'nodes':nodes,'indexes':indexes,'updated_at':time.time(),'changed':changed,'version':self.SCHEMA_VERSION}
