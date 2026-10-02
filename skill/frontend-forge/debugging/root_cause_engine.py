from .root_cause_verifier import verify_root_cause
def determine_root_cause(hypotheses,evidence,reproduction=None,*,expected_target=None,revision=None,workspace=None,task_id=None,task='',mode='SIMULATION',state_dir=None):
    if evidence and all(not isinstance(x,dict) for x in evidence):
        text=' '.join(map(str,evidence)).lower(); hs=list(hypotheses or [])
        if not hs:return {'status':'ROOT_CAUSE_UNVERIFIED','root_cause':None,'evaluations':[]}
        best=max(hs,key=lambda h:sum(1 for x in h.get('signal_matches',[]) if str(x).lower() in text))
        matches=sum(1 for x in best.get('signal_matches',[]) if str(x).lower() in text)
        return {'status':'ROOT_CAUSE_CONFIRMED' if matches else 'ROOT_CAUSE_UNVERIFIED','root_cause':best if matches else None,'evaluations':[]}
    ranked=[]
    for h in hypotheses or []:
        v=verify_root_cause(h,evidence,reproduction=reproduction,expected_target=expected_target,revision=revision,workspace=workspace,task_id=task_id,task=task,mode=mode,state_dir=state_dir)
        rank={'ROOT_CAUSE_CONFIRMED':4,'ROOT_CAUSE_PROBABLE':3,'ROOT_CAUSE_UNVERIFIED':2,'ROOT_CAUSE_REJECTED':1}.get(v['status'],0)
        ranked.append((rank,v,h))
    if not ranked:return {'status':'ROOT_CAUSE_UNVERIFIED','root_cause':None,'evaluations':[]}
    ranked.sort(key=lambda x:(x[0],x[1].get('causal_confidence',0)),reverse=True)
    best=ranked[0]
    return {'status':best[1]['status'],'root_cause':{**best[2],**{k:v for k,v in best[1].items() if k not in {'status'}}},'evaluations':[x[1] for x in ranked]}
