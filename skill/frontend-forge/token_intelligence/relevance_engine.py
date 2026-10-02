from pathlib import Path
DEFAULT_EXCLUDES=('node_modules/','dist/','build/','coverage/','.next/','.git/','vendor/')
LOCKS=('package-lock.json','pnpm-lock.yaml','yarn.lock')
GENERATED_HINTS=('.min.js','.min.css','.generated.','/generated/','/dist/','/build/')

def excluded(path,task=''):
    s=str(path).replace('\\','/');low=s.lower();t=(task or '').lower()
    if any(x in low for x in DEFAULT_EXCLUDES):return True,'default-generated/vendor exclusion'
    if Path(s).name in LOCKS and not any(x in t for x in ['dependency','lockfile','package install','supply chain']):return True,'lockfile excluded for unrelated task'
    if any(x in low for x in GENERATED_HINTS):return True,'generated/minified asset'
    return False,''

def score(candidate,task='',changed_files=()):
    p=candidate.get('path','');why=[];s=0
    ex,reason=excluded(p,task)
    if ex:return {'score':-100,'priority':'P4','reason':[reason]}
    lowtask=(task or '').lower();name=Path(p).name.lower()
    if p in candidate.get('targets',[]):s+=100;why.append('direct task target')
    if p in changed_files:s+=80;why.append('changed file')
    if name and name in lowtask:s+=55;why.append('task mention')
    if candidate.get('direct_dependency'):s+=45;why.append('direct dependency')
    if candidate.get('reverse_dependency'):s+=40;why.append('reverse dependency')
    if candidate.get('component_relationship'):s+=30;why.append('component relationship')
    if candidate.get('route_relationship'):s+=24;why.append('route relationship')
    if candidate.get('test_relationship'):s+=22;why.append('test relationship')
    if candidate.get('style_relationship'):s+=18;why.append('style relationship')
    if candidate.get('architecture_relevance'):s+=16;why.append('architecture relevance')
    if candidate.get('recent_decision'):s+=12;why.append('recent decision')
    pr='P0' if s>=80 else 'P1' if s>=40 else 'P2' if s>=20 else 'P3' if s>0 else 'P4'
    return {'score':s,'priority':pr,'reason':why or ['no positive relevance signal']}

def rank(candidates,task='',changed_files=()):
    rows=[]
    for c in candidates:
        r=score(c,task,changed_files);rows.append({**c,**r})
    return sorted(rows,key=lambda x:(-x['score'],x.get('path','')))
