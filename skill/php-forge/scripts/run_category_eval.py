#!/usr/bin/env python3
from pathlib import Path
import argparse,json,sys,re
BASE=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(BASE))
from router.engine import route

def rn(x):return int(x[1:])
def load():
    rows=[]
    for split in ['development','validation']:
        p=BASE/'evals'/split/'cases.jsonl'
        rows += [json.loads(x) for x in p.read_text(encoding='utf-8').splitlines() if x.strip()]
    return rows
ap=argparse.ArgumentParser();ap.add_argument('--category');ap.add_argument('--language',choices=['fa-mixed']);a=ap.parse_args()
rows=load()
if a.category: rows=[x for x in rows if x['category']==a.category]
if a.language: rows=[x for x in rows if re.search(r'[\u0600-\u06ff]',x['task'])]
f=[]
for x in rows:
    r=route(x['task']);mods={m['id'] for m in r['modules']};cons={c['id'] for c in r['concepts']};e=x['expected'];why=[]
    if not set(e.get('must_modules',[]))<=mods:why.append('module')
    if e.get('must_concepts') and not set(e['must_concepts'])<=cons:why.append('concept')
    if rn(r['risk']['level'])<rn(e.get('risk_min','R0')):why.append('risk_min')
    if e.get('risk_max') and rn(r['risk']['level'])>rn(e['risk_max']):why.append('risk_max')
    if set(e.get('must_not_modules',[]))&mods:why.append('forbidden')
    if why:f.append({'id':x['id'],'why':why})
out={'filter':a.category or a.language,'cases':len(rows),'passed':len(rows)-len(f),'accuracy':round((len(rows)-len(f))/max(len(rows),1)*100,2),'failures':f[:30]}
print(json.dumps(out,indent=2));raise SystemExit(1 if f else 0)
