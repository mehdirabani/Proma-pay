#!/usr/bin/env python3
import sys,json
from pathlib import Path
BASE=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(BASE));from router.engine import route
cases=[
('Fix an open redirect in the login return URL',{'security'}),('Investigate XXE in XML import parser',{'security'}),('Mass assignment lets users set is_admin',{'security'}),('User input reaches shell_exec without escaping',{'security'}),('Rotate the encryption key used for stored secrets',{'security'}),('کاربر با عوض کردن شناسه می‌تواند فاکتور بقیه را ببیند',{'security'}),('ورودی کاربر مستقیم وارد shell_exec می‌شود',{'security'}),('توکن بازیابی رمز عبور قابل حدس زدن است',{'security'}),('The balance can go negative when two withdrawals race',{'fintech','testing'}),('دو درخواست همزمان باعث کم شدن دوباره موجودی مشتری می‌شود',{'fintech','testing'}),('This is Symfony, not Laravel. Add a controller.',{'symfony'}),('Change the wallet icon SVG. No business logic changes.',set())]
f=[]
for task,must in cases:
 r=route(task);mods={x['id'] for x in r['modules']}
 if not must<=mods or ('Laravel' in task and 'not Laravel' in task and 'laravel' in mods) or ('wallet icon' in task.lower() and 'fintech' in mods):f.append({'task':task,'must':sorted(must),'got':sorted(mods)})
print(json.dumps({'cases':len(cases),'failures':f,'status':'PASS' if not f else 'FAIL'},ensure_ascii=False,indent=2));raise SystemExit(1 if f else 0)
