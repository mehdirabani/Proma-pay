#!/usr/bin/env python3
from .common import REG,MOD,norm

def exclusions(task):
    t=norm(task); excluded={}; clauses=[x.strip() for x in __import__('re').split(r'[.;!?\n]+',t) if x.strip()]
    for mid,m in MOD.items():
        aliases=sum((m.get('aliases') or {}).values(),[])
        for a in aliases:
            na=norm(a)
            for cl in clauses:
                if na not in cl: continue
                for p in REG['negation']['prefixes']:
                    np=norm(p)
                    if (np+' '+na) in cl or cl.startswith(np) and na in cl:
                        excluded[mid]='explicit negation'; break
                for r in REG['negation']['relations']:
                    nr=norm(r)
                    if na in cl and nr in cl: excluded[mid]='explicit unrelated relation'; break
                for suf in REG['negation'].get('suffixes',[]):
                    ns=norm(suf); pos=cl.find(na)
                    if pos>=0 and ns in cl[pos+len(na):]: excluded[mid]='explicit suffix negation'; break
    return excluded
