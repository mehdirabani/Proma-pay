#!/usr/bin/env python3
from .common import REG,MOD,norm,detect_language,level_num
from .semantic import extract_concepts
from .scope import classify_scope
from .intent import classify_intent
from .negation import exclusions
from .risk import classify_risk
from .context import select

def _repo_modules(repo):
    out={}
    if not repo:return out
    for f in repo.get('frameworks',[]):
        mid=f.get('name')
        if mid in MOD:
            lvl=f.get('confidence_level','low'); conf={'confirmed':1.0,'high':.92,'medium':.7,'low':.48,'unknown':.25,'conflicting':.35}.get(lvl,.5)
            out[mid]={'relevance':max(.55,conf),'repository_confidence':conf,'reason':['repository evidence']}
    return out

def route(task,repository=None):
    concepts=extract_concepts(task); scope=classify_scope(task); intent=classify_intent(task); excluded=exclusions(task)
    # Textual/UI scopes suppress weak latent semantic domain activation. Exact safety evidence survives.
    if scope['name'] in REG['risk_policy']['textual_scopes']:
        concepts={cid:hit for cid,hit in concepts.items() if hit.get('method')=='registry-alias' and REG['concepts'][cid].get('safety_critical')}
    module_scores=_repo_modules(repository)
    for cid,hit in concepts.items():
        c=REG['concepts'][cid]
        for mid in c.get('modules',[]):
            if mid not in MOD: continue
            cur=module_scores.setdefault(mid,{'relevance':0,'repository_confidence':.8,'reason':[]})
            cur['relevance']=max(cur['relevance'],float(hit.get('heuristic_score',.5)))
            cur['reason'].append('concept:'+cid)
            if c.get('safety_critical'): cur['safety_override']=True
    # explicit module aliases are data-driven evidence, not domain code.
    t=norm(task)
    for mid,m in MOD.items():
        hits=[]
        for vals in (m.get('aliases') or {}).values():
            hits += [x for x in vals if norm(x) in t]
        if hits and mid not in excluded and (not concepts or m.get('framework')):
            cur=module_scores.setdefault(mid,{'relevance':0,'repository_confidence':.75,'reason':[]})
            cur['relevance']=max(cur['relevance'],.78); cur['reason'].append('explicit module alias')
    conflicts=[]
    if repository:
        repo_fw={x.get('name') for x in repository.get('frameworks',[]) if x.get('confidence_level') in ['confirmed','high']}
        task_fw=set()
        for mid,m in MOD.items():
            if not m.get('framework') or mid in excluded: continue
            if any(norm(a) in t for vals in (m.get('aliases') or {}).values() for a in vals): task_fw.add(mid)
        for a in task_fw:
            ga=MOD[a].get('exclusive_group')
            if not ga: continue
            for b in repo_fw:
                if b in MOD and b!=a and MOD[b].get('exclusive_group')==ga:
                    conflicts.append({'type':'framework','task_claim':a,'repository_evidence':b,'status':'conflicting'})
                    if a in module_scores: module_scores[a]['relevance']=min(module_scores[a]['relevance'],.55);module_scores[a]['reason'].append('repository conflict')
                    if b in module_scores: module_scores[b]['relevance']=min(module_scores[b]['relevance'],.55);module_scores[b]['reason'].append('task conflict')
    risk=classify_risk(concepts,scope,repository)
    chosen,used,budget=select(module_scores,risk['level'],scope['name'],excluded)
    verification=[]
    for cid in concepts: verification += REG['concepts'][cid].get('verification',[])
    for mid in chosen: verification += MOD[mid].get('verification',{}).get('required',[])
    verification=list(dict.fromkeys(verification))
    workflow=REG['workflow_map'].get(intent['name'],REG['workflow_map']['analyze'])
    modules=[]
    for mid in chosen:
        m=module_scores.get(mid,{'relevance':.65,'reason':['dependency/risk policy']}); s=m.get('relevance',.65)
        modules.append({'id':mid,'confidence':'high' if s>=.75 else ('medium' if s>=.45 else 'low'),'heuristic_score':round(float(s),3),'reason':m.get('reason',['dependency/risk policy'])})
    return {
      'runtime':REG['runtime_version'],'language':detect_language(task),'intent':intent,'scope':scope,'risk':risk,
      'concepts':[{'id':cid,**hit} for cid,hit in sorted(concepts.items())],
      'modules':modules,'excluded_modules':[{'id':k,'reason':v} for k,v in excluded.items()],
      'selected_context':{'files':[workflow]+[MOD[x]['file'] for x in chosen],'estimated_context_tokens':used,'budget_tokens':budget,'metric':'registry-estimated tokens, not deployment tokenizer measurement'},
      'verification':verification,'autonomy':{'max':REG['risk_policy']['autonomy'][risk['level']],'reason':['bounded by task risk '+risk['level']]},
      'repository':repository or {},'conflicts':conflicts,'confidence_note':'Numeric values are heuristic routing scores, not calibrated probabilities.'}
