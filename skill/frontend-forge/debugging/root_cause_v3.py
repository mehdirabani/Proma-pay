from engineering_evidence.verifier_v3 import verify_evidence
from engineering_evidence.independence_v3 import independence

def diagnose(task,candidate,evidence,*,workspace,revision,task_id,state_dir=None):
 valid=[]
 for e in evidence:
  ok,_=verify_evidence(e,workspace=workspace,revision=revision,task_id=task_id,candidate=candidate,state_dir=state_dir)
  if ok:valid.append(e)
 findings=[];repros=[]
 for e in valid:
  if e.get('claim_type')=='ROOT_CAUSE_OBSERVATION':findings+=list((e.get('metadata') or {}).get('findings',[]))
  if e.get('claim_type')=='REPRODUCTION' and (e.get('metadata') or {}).get('reproduced'):repros.append(e)
 task_domains=set()
 low=task.lower()
 if any(x in low for x in ('overflow','mobile','viewport','موبایل','بیرون')):task_domains.add('responsive')
 if any(x in low for x in ('accessible','keyboard','label','دسترسی','کیبورد')):task_domains.add('accessibility')
 if any(x in low for x in ('route','link','مسیر')):task_domains.add('routing')
 if any(x in low for x in ('type','typescript','تایپ')):task_domains.add('typescript')
 if any(x in low for x in ('loading','async','لود')):task_domains.add('async-data')
 if any(x in low for x in ('submit','form','فرم')):task_domains.add('form')
 relevant=[f for f in findings if not task_domains or f.get('domain') in task_domains]
 incidental=[f for f in findings if f not in relevant]
 if not relevant:return {'status':'ROOT_CAUSE_UNVERIFIED','causal_tier':'HYPOTHESIS','root_cause':None,'incidental_findings':incidental,'evidence':valid}
 f=relevant[0];cause_code=f['cause_code'];repro=next((e for e in repros if (e.get('metadata') or {}).get('cause_code')==cause_code),None)
 tier='REPRODUCED' if repro else 'STATIC_ASSOCIATION';status='ROOT_CAUSE_CONFIRMED' if repro else 'ROOT_CAUSE_PROBABLE'
 rc={'hypothesis_id':'cause-'+cause_code.lower(),'cause_code':cause_code,'cause':f.get('mechanism'),'mechanism':f.get('mechanism'),'causal_tier':tier,'supporting_evidence_ids':[e.get('evidence_id') for e in valid if e.get('claim_type') in {'ROOT_CAUSE_OBSERVATION','REPRODUCTION'}]}
 return {'status':status,'causal_tier':tier,'root_cause':rc,'incidental_findings':incidental,'evidence':valid}
