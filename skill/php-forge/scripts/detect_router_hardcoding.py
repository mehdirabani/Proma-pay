#!/usr/bin/env python3
from pathlib import Path
import yaml,ast,sys
BASE=Path(__file__).resolve().parent.parent
reg=yaml.safe_load((BASE/'manifests/module-registry.yaml').read_text(encoding='utf-8'))
aliases=[]
for m in reg['modules']:
    for vals in m.get('aliases',{}).values(): aliases += [x.casefold() for x in vals if len(x)>=5]
allowed={'repository evidence','explicit module alias','framework','module','package','service','security','testing','database_signals','framework_version_check'}
viol=[]
scan=list((BASE/'router').glob('*.py'))+[BASE/'scripts/repo_intelligence_v3.py']
for p in scan:
    tree=ast.parse(p.read_text(encoding='utf-8'))
    strings=[n.value.casefold() for n in ast.walk(tree) if isinstance(n,ast.Constant) and isinstance(n.value,str)]
    for st in strings:
        if st in allowed: continue
        for a in aliases:
            if a in st and st not in [a]:
                viol.append((p.name,a,st[:80]))
            elif st==a:
                viol.append((p.name,a,st[:80]))
if viol:
    print('FAIL domain hard-coding:',viol[:20]);sys.exit(1)
print('PASS: domain knowledge is registry-driven in router/repository runtime')
