#!/usr/bin/env python3
import importlib,sys
req={'yaml':'PyYAML','sklearn':'scikit-learn'};missing=[]
for mod,pkg in req.items():
    try:importlib.import_module(mod)
    except Exception as e:missing.append({'module':mod,'package':pkg,'error':str(e)})
if missing:
    print('Missing runtime dependencies: '+', '.join(x['package'] for x in missing));sys.exit(2)
print('PASS: runtime dependencies available')
