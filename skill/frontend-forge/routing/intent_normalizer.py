from pathlib import Path
import json,re
ROOT=Path(__file__).resolve().parents[1]
REG=json.loads((ROOT/'routing/intent-registry.json').read_text())
def _norm(s):return re.sub(r'[\u200c\s]+',' ',(s or '').lower()).strip()
def normalize_intent(task):
    t=_norm(task);domains=[];evidence=[]
    for domain,cfg in REG.items():
        hits=[]
        for sig in cfg.get('signals_en',[])+cfg.get('signals_fa',[]):
            ns=_norm(sig)
            if ns and ns in t:hits.append(sig)
        if hits:domains.append(domain);evidence.extend([{'domain':domain,'signal':x} for x in hits])
    confidence=min(.98,.45+.12*len(evidence)) if evidence else .25
    operation='repair' if any(x in t for x in ['fix','repair','اصلاح','رفع']) else 'optimize' if any(x in t for x in ['optimize','بهتر','بهینه']) else 'change'
    return {'domains':sorted(set(domains)),'targets':re.findall(r'\b[A-Z][A-Za-z0-9_]+\b',task or ''),'operation':operation,'confidence':round(confidence,2),'evidence':evidence,'language':'fa' if re.search(r'[\u0600-\u06ff]',task or '') else 'en'}
