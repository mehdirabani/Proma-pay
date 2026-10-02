#!/usr/bin/env python3
from .common import REG,norm

def classify_intent(task):
    t=norm(task); best=('analyze',0,[])
    for iid,phrases in REG['intents'].items():
        hits=[p for p in phrases if norm(p) in t]
        score=max([len(norm(p)) for p in hits],default=0)
        if score>best[1]: best=(iid,score,hits[:3])
    return {'name':best[0],'confidence':'high' if best[1]>=5 else ('medium' if best[1] else 'low'),'evidence':best[2] or ['default intent']}
