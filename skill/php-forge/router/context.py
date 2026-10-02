#!/usr/bin/env python3
from .common import REG,MOD,level_num

def closure(ids):
    seen=set(); out=[]; stack=set()
    def visit(x):
        if x in seen:return
        if x in stack: raise RuntimeError('dependency cycle '+x)
        if x not in MOD: raise RuntimeError('missing dependency '+x)
        stack.add(x)
        for d in MOD[x].get('dependencies',{}).get('required',[]): visit(d)
        stack.remove(x); seen.add(x); out.append(x)
    for x in ids: visit(x)
    return out

def select(module_scores,risk,scope,excluded):
    required=set(REG['risk_policy']['risk_required_modules'].get(risk,[]))
    for mid in list(module_scores):
        if mid in excluded: module_scores.pop(mid,None)
    suppress=set(REG['scopes'].get(scope,{}).get('suppresses',[]))
    for mid in list(module_scores):
        if mid in suppress and not module_scores[mid].get('safety_override'): module_scores.pop(mid,None)
    ids=set(k for k,v in module_scores.items() if v.get('relevance',0)>=.32)|required
    ids=closure(ids)
    budget=int(REG['risk_policy']['budgets'][risk]); chosen=[]; used=0
    candidates=[]
    for mid in ids:
        m=MOD[mid]; rel=module_scores.get(mid,{}).get('relevance',.65); necessity=1.25 if mid in required else 1.0
        repo_conf=module_scores.get(mid,{}).get('repository_confidence',.8)
        riskw=1.35 if level_num(risk)>=4 and (module_scores.get(mid,{}).get('safety_override') or mid in required) else 1.0
        cost=int(m['estimated_tokens']); utility=(rel*riskw*necessity*repo_conf)/max(cost,1)
        candidates.append((utility,mid,cost))
    for _,mid,cost in sorted(candidates,reverse=True):
        if mid in required or used+cost<=budget:
            chosen.append(mid); used+=cost
    chosen=closure(chosen)
    return chosen,used,budget
