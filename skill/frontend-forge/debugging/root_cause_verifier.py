from engineering_evidence.verifier import verify_engineering_evidence
from engineering_evidence.independence import independence
from .causal_reasoning import classify_role,mechanism_for

def verify_root_cause(hypothesis,evidence,*,reproduction=None,expected_target=None,revision=None,workspace=None,task_id=None,task='',mode='SIMULATION',state_dir=None):
    supporting=[];contradicting=[];incidental=[];invalid=[]
    if mode!='PRODUCTION':
        # Legacy compatibility path.
        from .evidence import validate_evidence,normalized_terms,independent_key
        valid=[]
        for e in evidence or []:
            ok,reason=validate_evidence(e,candidate=expected_target,revision=revision,workspace=workspace,task_id=task_id)
            if not ok:invalid.append(reason);continue
            ht=normalized_terms(hypothesis.get('hypothesis',''));et=normalized_terms(str(e.get('observation',''))+' '+str(e.get('artifact','')))
            if ht&et:valid.append(e)
        direct=[e for e in valid if e.get('direct')]
        indep={independent_key(e) for e in valid}
        state='ROOT_CAUSE_CONFIRMED' if direct or len(indep)>=2 else 'ROOT_CAUSE_PROBABLE' if indep else 'ROOT_CAUSE_UNVERIFIED'
        return {'status':state,'relevant_evidence':valid,'invalid_evidence':invalid,'independent_evidence':len(indep),'direct_evidence':len(direct)}
    valid=[]
    for e in evidence or []:
        ok,reason=verify_engineering_evidence(e,workspace=workspace,revision=revision,task_id=task_id,candidate=expected_target,state_dir=state_dir)
        if not ok:invalid.append(reason);continue
        valid.append(e)
        role=classify_role(task,hypothesis,e)
        if role=='SUPPORTING':supporting.append(e)
        elif role=='CONTRADICTING':contradicting.append(e)
        else:incidental.append(e)
    independent=False
    for i,a in enumerate(supporting):
        for b in supporting[i+1:]:
            if independence(a,b)=='INDEPENDENT':independent=True
    if reproduction and reproduction.get('status')=='NOT_REPRODUCED':
        state='ROOT_CAUSE_REJECTED'
    elif contradicting and not supporting:
        state='ROOT_CAUSE_REJECTED'
    elif reproduction and reproduction.get('status')=='REPRODUCED' and supporting:
        state='ROOT_CAUSE_CONFIRMED'
    elif len(supporting)>=2 and independent:
        state='ROOT_CAUSE_CONFIRMED'
    elif supporting:
        state='ROOT_CAUSE_PROBABLE'
    else:
        state='ROOT_CAUSE_UNVERIFIED'
    mechanism=mechanism_for(task,hypothesis,supporting[0] if supporting else {})
    return {'status':state,'cause':hypothesis.get('hypothesis'),'mechanism':mechanism,'symptom':task,
            'supporting_evidence':supporting,'contradicting_evidence':contradicting,'incidental_evidence':incidental,
            'invalid_evidence':invalid,'causal_confidence':round(max(0,min(1,.35*len(supporting)-.4*len(contradicting)+(.3 if reproduction and reproduction.get('status')=='REPRODUCED' else 0))),3)}
