from .relevance_engine import rank
from .token_meter import DeterministicTokenEstimator
from .dedup import deduplicate

def _bounded_representation(c,budget,est):
    text=c.get('text','');cost=est.estimate(text).tokens
    if cost<=budget:return {**c,'estimated_tokens':cost,'representation':'full'}
    # Deterministic structure/symbol slice for huge required file.
    lines=text.splitlines();symbols=c.get('symbols') or []
    header='\n'.join(lines[:min(25,len(lines))]);sym='Symbols: '+', '.join(symbols[:40]) if symbols else ''
    tail='\n'.join(lines[-10:]) if len(lines)>35 else ''
    sliced='\n'.join(x for x in [header,sym,tail] if x)
    # Hard truncate by character approximation if still too large.
    maxchars=max(1,budget*3)
    sliced=sliced[:maxchars]
    scost=est.estimate(sliced).tokens
    while scost>budget and len(sliced)>1:
        sliced=sliced[:max(1,int(len(sliced)*.8))]
        scost=est.estimate(sliced).tokens
    return {**c,'text':sliced,'estimated_tokens':scost,'representation':'bounded-structure','original_estimated_tokens':cost}

def select(candidates,task,budget_tokens,changed_files=(),confidence=1.0):
    ranked=rank(candidates,task,changed_files);est=DeterministicTokenEstimator();chosen=[];rejected=[];used=0
    p0=[c for c in ranked if c['priority']=='P0']
    others=[c for c in ranked if c['priority']!='P0']
    for c in p0:
        remaining=max(0,budget_tokens-used);bounded=_bounded_representation(c,remaining,est)
        if bounded['estimated_tokens']>remaining or remaining<=0:
            return {'status':'CONTEXT_BUDGET_EXCEEDED','loaded':chosen,'rejected':rejected+[c], 'estimated_context_tokens':used,'needs_expansion':False,'decision_trace':[]}
        chosen.append(bounded);used+=bounded['estimated_tokens']
    for c in others:
        if c['priority']=='P4':rejected.append({**c,'exclude_reason':'P4'});continue
        cost=est.estimate(c.get('text','')).tokens
        if used+cost<=budget_tokens:chosen.append({**c,'estimated_tokens':cost,'representation':'full'});used+=cost
        else:rejected.append({**c,'exclude_reason':'budget'})
    dd=deduplicate(chosen);chosen=dd['kept'];rejected.extend([{**x,'exclude_reason':'duplicate'} for x in dd['duplicates']])
    status='INSUFFICIENT_CONTEXT' if confidence<.35 and not any(x.get('priority')=='P0' for x in chosen) else 'PASS'
    return {'status':status,'loaded':chosen,'rejected':rejected,'estimated_context_tokens':sum(est.estimate(x.get('text','')).tokens for x in chosen),'needs_expansion':confidence<.60,'decision_trace':[{'file':x.get('path'),'priority':x.get('priority'),'reason':x.get('reason'),'representation':x.get('representation')} for x in chosen]}

def minimum_sufficient_context(candidates,quality_fn,tolerance=0.0):
    current=list(candidates);baseline=quality_fn(current)
    for item in sorted(list(current),key=lambda x:x.get('score',0)):
        trial=[x for x in current if x is not item];q=quality_fn(trial)
        if q>=baseline-tolerance:current=trial
    return {'context':current,'baseline_quality':baseline,'final_quality':quality_fn(current)}
