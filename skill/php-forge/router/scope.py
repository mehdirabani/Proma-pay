#!/usr/bin/env python3
from .common import REG,norm
import re
def has_unit(t,x):
    x=norm(x)
    if not x:return False
    if re.search(r'[a-z0-9]',x): return bool(re.search(r'(?<!\w)'+re.escape(x)+r'(?!\w)',t))
    return x in t

def classify_scope(task):
    t=norm(task); best=('module',0,[])
    only=any(has_unit(t,x) for x in REG['negation'].get('only_markers',[]))
    for sid,s in REG['scopes'].items():
        hits=[p for p in s.get('phrases',[]) if has_unit(t,p)]
        if only:
            hits += [h for h in s.get('hints',[]) if has_unit(t,h)]
        if hits and int(s.get('rank',0))>=best[1]: best=(sid,int(s.get('rank',0)),hits[:3])
    return {'name':best[0],'confidence':'high' if best[1]>0 else 'low','evidence':best[2] or ['conservative default']}
