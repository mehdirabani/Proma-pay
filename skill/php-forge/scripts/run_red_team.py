#!/usr/bin/env python3
from pathlib import Path
import json,sys
BASE=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(BASE));from router.engine import route
p=BASE/'evals'/'red_team'/'blind-200.jsonl';rows=[json.loads(x) for x in p.read_text(encoding='utf-8').splitlines() if x.strip()];f=[]
for x in rows:
 mods={m['id'] for m in route(x['task'])['modules']}
 if x['must_module'] not in mods:f.append({'id':x['id'],'task':x['task'],'must':x['must_module'],'got':sorted(mods)})
out={'cases':len(rows),'passed':len(rows)-len(f),'accuracy':round((len(rows)-len(f))/len(rows)*100,2),'failures':f[:100]};(BASE/'reports'/'final-red-team.json').write_text(json.dumps(out,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(out,ensure_ascii=False,indent=2));raise SystemExit(1 if f else 0)
