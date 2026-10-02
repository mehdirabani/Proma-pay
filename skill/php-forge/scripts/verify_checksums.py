#!/usr/bin/env python3
from pathlib import Path
import json,hashlib,sys
BASE=Path(__file__).resolve().parent.parent
p=BASE/'manifests/checksums.json'
if not p.exists():print('FAIL missing checksums');sys.exit(1)
data=json.loads(p.read_text());bad=[]
for rel,want in data.get('files',{}).items():
    f=BASE/rel
    if not f.exists():bad.append((rel,'missing'));continue
    got=hashlib.sha256(f.read_bytes()).hexdigest()
    if got!=want:bad.append((rel,'mismatch'))
if bad:print('FAIL checksums',bad[:20]);sys.exit(1)
print('PASS checksums',len(data.get('files',{})))
