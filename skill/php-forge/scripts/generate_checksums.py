#!/usr/bin/env python3
from pathlib import Path
import hashlib,json
BASE=Path(__file__).resolve().parent.parent;out={}
for p in sorted(BASE.rglob('*')):
 if not p.is_file() or p.name=='checksums.json':continue
 if '__pycache__' in p.parts or p.suffix in ['.pyc','.pyo']:continue
 rel=str(p.relative_to(BASE));out[rel]=hashlib.sha256(p.read_bytes()).hexdigest()
(BASE/'manifests/checksums.json').write_text(json.dumps({'algorithm':'sha256','files':out},indent=2),encoding='utf-8');print('checksums',len(out))
