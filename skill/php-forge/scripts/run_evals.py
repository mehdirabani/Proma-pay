#!/usr/bin/env python3
import argparse,json,sys,re
from collections import defaultdict
from pathlib import Path
BASE=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(BASE))
from router.engine import route

def rn(r): return int(r[1:])
def has_persian(s): return bool(re.search(r'[\u0600-\u06ff]',s))
def safe_pct(a,b): return round(a/max(b,1)*100,2) if b else None

def run(split):
 p=BASE/'evals'/split/'cases.jsonl';rows=[json.loads(x) for x in p.read_text(encoding='utf-8').splitlines() if x.strip()]
 fails=[]
 metrics={'cases':len(rows),'passed':0,'module_tp':0,'module_fn':0,'module_fp':0,'security_critical_total':0,'security_critical_hit':0,'fintech_critical_total':0,'fintech_critical_hit':0}
 categories=defaultdict(lambda:{'cases':0,'passed':0})
 langs={'persian_or_mixed':{'cases':0,'passed':0},'english_only':{'cases':0,'passed':0}}
 risk_exact={'cases':0,'passed':0}
 for row in rows:
  got=route(row['task']);mods={x['id'] for x in got['modules']};cons={x['id'] for x in got['concepts']};exp=row['expected'];ok=True;why=[]
  must=set(exp.get('must_modules',[]));missing=must-mods
  if missing:ok=False;why.append('missing modules '+str(sorted(missing)))
  if exp.get('must_concepts'):
   mc=set(exp['must_concepts'])-cons
   if mc:ok=False;why.append('missing concepts '+str(sorted(mc)))
  if rn(got['risk']['level'])<rn(exp.get('risk_min','R0')):ok=False;why.append('risk '+got['risk']['level']+' < '+exp.get('risk_min','R0'))
  if 'risk_max' in exp and rn(got['risk']['level'])>rn(exp['risk_max']):ok=False;why.append('risk '+got['risk']['level']+' > '+exp['risk_max'])
  forbidden=set(exp.get('must_not_modules',[]))&mods
  if forbidden:ok=False;why.append('forbidden modules '+str(sorted(forbidden)))
  allowed=must | set(exp.get('allowed_modules',[]))
  metrics['module_tp']+=len(must&mods);metrics['module_fn']+=len(must-mods);metrics['module_fp']+=len(mods-allowed)
  if rn(exp.get('risk_min','R0'))>=4 and 'security' in must:
   metrics['security_critical_total']+=1;metrics['security_critical_hit']+=int('security' in mods)
  if rn(exp.get('risk_min','R0'))>=4 and 'fintech' in must:
   metrics['fintech_critical_total']+=1;metrics['fintech_critical_hit']+=int('fintech' in mods)
  if 'risk_exact' in exp:
   risk_exact['cases']+=1;risk_exact['passed']+=int(got['risk']['level']==exp['risk_exact'])
   if got['risk']['level']!=exp['risk_exact']:
    ok=False;why.append('risk '+got['risk']['level']+' != '+exp['risk_exact'])
  categories[row['category']]['cases']+=1
  lang='persian_or_mixed' if has_persian(row['task']) else 'english_only';langs[lang]['cases']+=1
  if ok:
   metrics['passed']+=1;categories[row['category']]['passed']+=1;langs[lang]['passed']+=1
  elif len(fails)<100:
   fails.append({'id':row['id'],'task':row['task'],'category':row['category'],'concept_group':row.get('concept_group'),'why':why,'route':{'risk':got['risk'],'scope':got.get('scope'),'modules':sorted(mods),'concepts':sorted(cons)}})
 metrics['accuracy']=safe_pct(metrics['passed'],metrics['cases'])
 metrics['security_critical_recall']=safe_pct(metrics['security_critical_hit'],metrics['security_critical_total'])
 metrics['fintech_critical_recall']=safe_pct(metrics['fintech_critical_hit'],metrics['fintech_critical_total'])
 den=metrics['module_tp']+metrics['module_fp'];metrics['domain_precision']=round(metrics['module_tp']/den*100,2) if den else 100
 den=metrics['module_tp']+metrics['module_fn'];metrics['domain_recall']=round(metrics['module_tp']/den*100,2) if den else 100
 cat_out={k:{**v,'accuracy':safe_pct(v['passed'],v['cases'])} for k,v in sorted(categories.items())}
 lang_out={k:{**v,'accuracy':safe_pct(v['passed'],v['cases'])} for k,v in langs.items()}
 risk_exact['accuracy']=safe_pct(risk_exact['passed'],risk_exact['cases'])
 out={'split':split,'status':'PASS' if not fails else 'FAIL','metrics':metrics,'category_metrics':cat_out,'language_metrics':lang_out,'risk_exact_metrics':risk_exact,'failures':fails}
 (BASE/'reports'/f'{split}-results.json').write_text(json.dumps(out,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(out,ensure_ascii=False,indent=2));return 0 if not fails else 1
if __name__=='__main__':
 ap=argparse.ArgumentParser();ap.add_argument('--split',choices=['development','validation','hidden'],required=True);a=ap.parse_args();raise SystemExit(run(a.split))
