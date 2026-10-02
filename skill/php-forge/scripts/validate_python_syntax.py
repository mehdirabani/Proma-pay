#!/usr/bin/env python3
from pathlib import Path
import ast,sys
BASE=Path(__file__).resolve().parent.parent;bad=[]
for d in ['router','scripts','tests','benchmarks']:
    for p in (BASE/d).rglob('*.py'):
        try:ast.parse(p.read_text(encoding='utf-8'),filename=str(p))
        except SyntaxError as e:bad.append(f'{p.relative_to(BASE)}:{e.lineno}:{e.msg}')
if bad:
    print('FAIL',bad);sys.exit(1)
print('PASS: Python syntax')
