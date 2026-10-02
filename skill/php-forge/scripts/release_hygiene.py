#!/usr/bin/env python3
from pathlib import Path
import sys
BASE=Path(__file__).resolve().parent.parent
bad=[]
for p in BASE.rglob('*'):
    if '__pycache__' in p.parts or p.suffix in ['.pyc','.pyo'] or p.name in ['.coverage','.env','.env.local','.env.production'] or '.pytest_cache' in p.parts:
        bad.append(str(p.relative_to(BASE)))
if bad:
    print('FAIL release hygiene',bad[:30]);sys.exit(1)
print('PASS release hygiene')
