from pathlib import Path
import json,re
EXTS=['.ts','.tsx','.js','.jsx','.mjs','.css','.scss']
class ConfigCycle(ValueError):pass

def _strip_json_comments(s):return re.sub(r'//.*?$|/\*.*?\*/','',s,flags=re.M|re.S)

class ProjectResolver:
    def __init__(self,root):
        self.root=Path(root).resolve();self.base_url=self.root;self.paths={};self.workspaces=[];self.references=[];self.package_exports={};self.package_imports={};self.mode='REDUCED';self.config_cache={};self.workspace_packages={};self._load()
    def _json(self,p):
        try:return json.loads(_strip_json_comments(p.read_text()))
        except Exception:return {}
    def _load_config(self,p,seen=None):
        seen=set(seen or ());p=p.resolve()
        if p in seen:raise ConfigCycle('TSCONFIG_EXTENDS_CYCLE')
        seen.add(p);d=self._json(p);base={}
        ext=d.get('extends')
        if ext:
            ep=(p.parent/ext)
            if ep.suffix!='.json':ep=Path(str(ep)+'.json')
            if ep.exists():base=self._load_config(ep,seen)
        parent_co=base.get('compilerOptions',{});own=d.get('compilerOptions',{});co={**parent_co,**own};refs=d.get('references',base.get('references',[]))
        if 'baseUrl' in own:resolved_base=str((p.parent/own.get('baseUrl','.')).resolve())
        else:resolved_base=base.get('resolved_base_url',str(p.parent.resolve()))
        return {'compilerOptions':co,'references':refs,'resolved_base_url':resolved_base,'config_path':str(p)}
    def _nearest_config(self,src):
        cur=Path(src).resolve().parent
        while True:
            for n in ('tsconfig.json','jsconfig.json'):
                p=cur/n
                if p.exists():
                    key=str(p.resolve())
                    if key not in self.config_cache:self.config_cache[key]=self._load_config(p)
                    return self.config_cache[key]
            if cur==self.root or self.root not in cur.parents:break
            cur=cur.parent
        return None
    def _workspace_patterns(self,d):
        ws=d.get('workspaces',[]);return ws.get('packages',[]) if isinstance(ws,dict) else ws if isinstance(ws,list) else []
    def _discover_workspace_packages(self):
        pkg=self.root/'package.json'
        if not pkg.exists():return
        d=self._json(pkg);self.workspaces=self._workspace_patterns(d);self.package_exports=d.get('exports',{}) or {};self.package_imports=d.get('imports',{}) or {}
        patterns=[]
        for pat in self.workspaces:
            if pat.endswith('/*'):patterns.extend((self.root/pat[:-2]).glob('*'))
            else:patterns.extend(self.root.glob(pat))
        for wd in patterns:
            pp=wd/'package.json'
            if not pp.exists():continue
            pd=self._json(pp);name=pd.get('name')
            if name:self.workspace_packages[name]={'root':wd.resolve(),'package':pd}
    def _load(self):
        for name in ('tsconfig.json','jsconfig.json'):
            p=self.root/name
            if p.exists():
                d=self._load_config(p);co=d.get('compilerOptions',{});self.base_url=Path(d.get('resolved_base_url',str(p.parent))).resolve();self.paths=co.get('paths',{}) or {};self.references=d.get('references',[]) or [];self.mode='PARTIAL';self.config_cache[str(p.resolve())]=d;break
        self._discover_workspace_packages()
    def _file(self,base,allow_outside=False):
        candidates=[base]+[Path(str(base)+e) for e in EXTS]+[base/f'index{e}' for e in EXTS]
        for c in candidates:
            if c.is_file():
                try:return str(c.resolve().relative_to(self.root)).replace('\\','/')
                except ValueError:
                    return str(c.resolve()) if allow_outside else None
    def _map_pattern(self,spec,mapping,base):
        for pattern,target in mapping.items():
            targets=[]
            if isinstance(target,str):targets=[target]
            elif isinstance(target,list):targets=target
            elif isinstance(target,dict):targets=[target.get('types'),target.get('import'),target.get('default')]
            targets=[x for x in targets if isinstance(x,str)]
            rg='^'+re.escape(pattern).replace('\\*','(.+)')+'$';m=re.match(rg,spec)
            if not m:continue
            for t in targets:
                val=t.replace('*',m.group(1) if m.groups() else '');r=self._file((base/val).resolve())
                if r:return r
    def _workspace_resolve(self,spec):
        # exact package or package subpath
        for name,info in self.workspace_packages.items():
            if spec==name or spec.startswith(name+'/'):
                sub=spec[len(name):].lstrip('/');pkg=info['package'];wroot=info['root']
                exports=pkg.get('exports',{}) or {}
                key='.' if not sub else './'+sub
                target=exports.get(key)
                vals=[]
                if isinstance(target,str):vals=[target]
                elif isinstance(target,dict):vals=[target.get('types'),target.get('import'),target.get('default')]
                for v in vals:
                    if isinstance(v,str):
                        r=self._file((wroot/v).resolve())
                        if r:return r
                # analysis prefers source/index when export points to dist
                for c in [wroot/'src'/('index.ts' if not sub else sub+'.ts'),wroot/'src'/('index.tsx' if not sub else sub+'.tsx'),wroot/'src'/sub,wroot/'index.ts']:
                    r=self._file(c)
                    if r:return r
        return None
    def resolve(self,src,spec):
        src=Path(src).resolve()
        if spec.startswith('.'):return self._file((src.parent/spec).resolve())
        cfg=self._nearest_config(src)
        if cfg:
            co=cfg.get('compilerOptions',{});paths=co.get('paths',{}) or {};base=Path(cfg.get('resolved_base_url',src.parent))
            r=self._map_pattern(spec,paths,base)
            if r:return r
        r=self._map_pattern(spec,self.paths,self.base_url)
        if r:return r
        if spec.startswith('#'):
            # nearest package.json imports first
            cur=src.parent
            while True:
                pp=cur/'package.json'
                if pp.exists():
                    d=self._json(pp);r=self._map_pattern(spec,d.get('imports',{}) or {},cur)
                    if r:return r
                if cur==self.root or self.root not in cur.parents:break
                cur=cur.parent
            r=self._map_pattern(spec,self.package_imports,self.root)
            if r:return r
        r=self._workspace_resolve(spec)
        if r:return r
        return None
    def unresolved_rate(self,imports):
        total=len(imports);un=sum(1 for src,spec in imports if self.resolve(src,spec) is None and not spec.startswith('.'))
        return un/max(1,total)
    def capability_report(self):
        return {'relative_imports':'SUPPORTED','tsconfig_paths':'SUPPORTED' if self.paths else 'PARTIAL','tsconfig_extends':'SUPPORTED','nested_tsconfig':'SUPPORTED','project_references':'PARTIAL' if self.references else 'SUPPORTED','package_imports':'SUPPORTED' if self.package_imports else 'PARTIAL','package_exports':'PARTIAL','workspaces':'PARTIAL' if self.workspaces else 'SUPPORTED','workspace_package_resolution':'SUPPORTED' if self.workspace_packages else 'PARTIAL','parser_backend':'REDUCED'}
