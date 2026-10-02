#!/usr/bin/env python3
import argparse,json,re,subprocess
from pathlib import Path
import yaml
BASE=Path(__file__).resolve().parent.parent
REG=yaml.safe_load((BASE/'manifests/module-registry.yaml').read_text(encoding='utf-8'))

def jload(p):
    try:return json.loads(p.read_text(encoding='utf-8'))
    except Exception:return {}
def level(score,conflict=False):
    if conflict:return 'conflicting'
    if score>=.98:return 'confirmed'
    if score>=.8:return 'high'
    if score>=.55:return 'medium'
    if score>0:return 'low'
    return 'unknown'
def boundaries(root,maxdepth=5):
    out=[]
    for p in root.rglob('composer.json'):
        try:r=p.parent.relative_to(root)
        except:continue
        if len(r.parts)<=maxdepth:out.append(str(r) if str(r)!='.' else '.')
    return sorted(set(out))
def package_version(lock,name):
    for x in (lock.get('packages') or [])+(lock.get('packages-dev') or []):
        if x.get('name')==name:return x.get('version')
    return None
def parse_php_assignment(path,var):
    try:s=path.read_text(encoding='utf-8',errors='ignore')[:200000]
    except:return None
    m=re.search(r'\$'+re.escape(var)+r"\s*=\s*['\"]([^'\"]+)",s)
    return m.group(1) if m else None
def plugin_header_version(path):
    try:s=path.read_text(encoding='utf-8',errors='ignore')[:30000]
    except:return None
    m=re.search(r'^\s*\*?\s*Version\s*:\s*([^\r\n]+)',s,re.I|re.M)
    return m.group(1).strip() if m else None

def inspect(repo,target=None):
    root=Path(repo).resolve(); targetp=(root/target).resolve() if target else root
    bs=boundaries(root); candidates=[]
    for b in bs:
        bp=root if b=='.' else root/b
        try:targetp.relative_to(bp);candidates.append(bp)
        except:pass
    pkgroot=max(candidates,key=lambda p:len(p.parts)) if candidates else root
    comp=jload(pkgroot/'composer.json'); lock=jload(pkgroot/'composer.lock')
    req={}; req.update(comp.get('require') or {}); req.update(comp.get('require-dev') or {})
    installed={x.get('name'):x.get('version') for x in (lock.get('packages') or [])+(lock.get('packages-dev') or []) if x.get('name')}
    pkgs={**req,**installed}; frameworks=[]
    for m in REG['modules']:
        if not m.get('framework'):continue
        score=0;e=[];version=None
        for p in m.get('repository_signals',{}).get('packages',[]):
            if p in installed:score=max(score,.99);version=installed[p];e.append({'type':'lock_package','value':p,'weight':.99})
            elif p in req:score=max(score,.72);version=req[p];e.append({'type':'manifest_package','value':p,'weight':.72})
        for f in m.get('repository_signals',{}).get('files',[]):
            if (pkgroot/f).exists():score=max(score,.58);e.append({'type':'filesystem','value':f,'weight':.58})
        for probe in m.get('version_probes',[]):
            pc=float(probe.get('confidence',.8))
            if probe.get('type')=='php_assignment':
                v=parse_php_assignment(pkgroot/probe['file'],probe['variable'])
                if v: version=v;score=max(score,pc);e.append({'type':'source_constant','value':probe['variable'],'weight':pc})
            elif probe.get('type')=='plugin_header':
                for rel in probe.get('files',[]):
                    cp=pkgroot/rel
                    if cp.exists():
                        v=plugin_header_version(cp)
                        if v: version=v;score=max(score,pc);e.append({'type':'plugin_header','value':rel,'weight':pc})
        if score:frameworks.append({'name':m['id'],'version':version,'confidence_level':level(score),'score_heuristic':score,'evidence':e})
    runtime={'php':{'version':None,'confidence':'unknown'},'extensions':[]}
    try:
        v=subprocess.check_output(['php','-r','echo PHP_VERSION;'],text=True,stderr=subprocess.DEVNULL,timeout=3).strip();runtime['php']={'version':v,'confidence':'confirmed'}
        runtime['extensions']=json.loads(subprocess.check_output(['php','-r','echo json_encode(get_loaded_extensions());'],text=True,stderr=subprocess.DEVNULL,timeout=3))
    except Exception:pass
    ri=REG.get('repository_intelligence',{})
    tools={}
    for name,cfg in ri.get('tools',{}).items():
        for package in cfg.get('packages',[]):
            if package in pkgs: tools[name]={'version':pkgs[package],'source':'lock' if package in installed else 'manifest'}
        if name not in tools and any((pkgroot/f).exists() for f in cfg.get('configs',[])): tools[name]={'version':None,'source':'config'}
    ci=[x for x in ri.get('ci_paths',[]) if (pkgroot/x).exists()]
    tech={}
    for group,mapping in ri.get('technology_signals',{}).items():
        tech[group]=[label for package,label in mapping.items() if package in pkgs]
    cli={name:(pkgroot/rel).exists() for name,rel in ri.get('cli_files',{}).items()}
    envs=[x for x in ri.get('environment_presence_files',[]) if (pkgroot/x).exists()]
    return {'root':str(root),'target_path':str(targetp),'package_root':str(pkgroot),'package_boundaries':bs,'is_monorepo':len(bs)>1,'php_constraint':req.get('php') or (comp.get('config',{}).get('platform',{}) or {}).get('php'),'runtime':runtime,'frameworks':frameworks,'tools':tools,'ci':ci,'technology_signals':tech,'cli':cli,'environment_files_present':envs,'secrets_loaded':False,'evidence_priority':['runtime','lock','manifest','source_constant','filesystem','task_text','generic_assumption']}
if __name__=='__main__':
    ap=argparse.ArgumentParser();ap.add_argument('repo');ap.add_argument('--target');a=ap.parse_args();print(json.dumps(inspect(a.repo,a.target),ensure_ascii=False,indent=2))
