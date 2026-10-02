#!/usr/bin/env python3
import sys,json
from pathlib import Path
BASE=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(BASE));from router.engine import route
cases={
'idempotency':['Gateway response is delivered again and creates a second transaction','Retrying the callback applies the same charge twice','درگاه دوباره callback می‌زند و تراکنش تکراری می‌سازد','pardakht dobar sabt mishe'],
'idor':['Changing the invoice number reveals a different customer record','Object lookup trusts a customer supplied id without ownership check','با تغییر شماره فاکتور اطلاعات مشتری دیگه باز میشه','user mitone factor baghie ro bebine'],
'command_injection':['A request parameter is interpolated into a shell command','External text is passed to an OS process without argument separation','ورودی فرم وارد دستور سیستم میشه']}
expected={'idempotency':'fintech','idor':'security','command_injection':'security'};fail=[]
for concept,tasks in cases.items():
 for t in tasks:
  r=route(t);mods={x['id'] for x in r['modules']}
  if expected[concept] not in mods:fail.append({'concept':concept,'task':t,'got':sorted(mods)})
print(json.dumps({'cases':sum(map(len,cases.values())),'failures':fail,'status':'PASS' if not fail else 'FAIL'},ensure_ascii=False,indent=2));raise SystemExit(1 if fail else 0)
