from engineering_evidence.verifier import verify_engineering_evidence
from engineering_evidence.independence import independence
CONFIRMED='TARGET_CONFIRMED';PROBABLE='TARGET_PROBABLE';AMBIGUOUS='TARGET_AMBIGUOUS';NOT_FOUND='TARGET_NOT_FOUND';INVALID='INVALID_TARGET_EVIDENCE'
def assess_target(candidate,*,target_evidence=None,task_evidence=None,dependency_evidence=None,repository_evidence=None,
                  retrieval_status=None,revision='UNKNOWN',workspace='',task_id='task',mode='SIMULATION',state_dir=None):
    if not candidate or retrieval_status=='TARGET_NOT_FOUND':return {'state':NOT_FOUND,'allow_edit':False,'independent_evidence':0,'reasons':['no target']}
    path=candidate.get('path') if isinstance(candidate,dict) else str(candidate)
    conf=float(candidate.get('confidence',candidate.get('calibrated_confidence',0))) if isinstance(candidate,dict) else 0
    if any(isinstance(x,bool) for x in (task_evidence,dependency_evidence,repository_evidence)):
        return {'state':INVALID,'allow_edit':False,'independent_evidence':0,'reasons':['boolean evidence rejected']}
    evidence=list(target_evidence or [])
    for x in (task_evidence,dependency_evidence,repository_evidence):
        if isinstance(x,dict):evidence.append(x)
        elif isinstance(x,list):evidence.extend(x)
    if mode!='PRODUCTION':
        # Legacy simulation compatibility: self-hashed records are allowed only outside production.
        from engineering_intelligence.target_evidence import validate_target_evidence,independent_key
        valid=[];errors=[]
        for e in evidence:
            ok,reason=validate_target_evidence(e,candidate=path,revision=revision,workspace=str(workspace),task_id=task_id)
            if ok:valid.append(e)
            else:errors.append(reason)
        if errors and not valid:return {'state':INVALID,'allow_edit':False,'independent_evidence':0,'reasons':errors}
        indep={independent_key(e) for e in valid}
        sources={e.get('source') for e in valid};kinds={e.get('kind') for e in valid}
        if len(indep)>=2 and len(sources|kinds)>=2 and conf>=.65:
            return {'state':CONFIRMED,'allow_edit':True,'independent_evidence':len(indep),'reasons':['simulation evidence verified'],'target':path,'evidence':valid,'confidence':conf}
        return {'state':PROBABLE if valid else AMBIGUOUS,'allow_edit':False,'independent_evidence':len(indep),'reasons':['simulation evidence insufficient'],'target':path,'evidence':valid,'confidence':conf}
    valid=[];errors=[]
    for e in evidence:
        ok,reason=verify_engineering_evidence(e,workspace=workspace,revision=revision,task_id=task_id,candidate=path,state_dir=state_dir)
        if ok:valid.append(e)
        else:errors.append(reason)
    if errors and not valid:return {'state':INVALID,'allow_edit':False,'independent_evidence':0,'reasons':errors}
    if retrieval_status in {'TARGET_AMBIGUOUS','TARGET_LOW_CONFIDENCE'}:
        return {'state':AMBIGUOUS,'allow_edit':False,'independent_evidence':0,'reasons':['retrieval ambiguous'],'evidence':valid}
    task_links=[e for e in valid if (e.get('task_relevance') or {}).get('task_linking') and (e.get('task_relevance') or {}).get('score',0)>=.45]
    corroborating=[]
    for e in valid:
        if e in task_links:continue
        if any(independence(e,t)=='INDEPENDENT' for t in task_links):corroborating.append(e)
    if task_links and corroborating and conf>=.55:
        return {'state':CONFIRMED,'allow_edit':True,'independent_evidence':2,'reasons':['attested relevant independent evidence'],
                'target':path,'evidence':valid,'confidence':conf}
    if valid:
        return {'state':PROBABLE,'allow_edit':False,'independent_evidence':0,'reasons':['task-link or independent corroboration missing'],
                'target':path,'evidence':valid,'confidence':conf}
    return {'state':AMBIGUOUS,'allow_edit':False,'independent_evidence':0,'reasons':['confidence alone insufficient'],'target':path,'evidence':[],'confidence':conf}
