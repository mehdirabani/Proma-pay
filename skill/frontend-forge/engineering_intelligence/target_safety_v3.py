from engineering_evidence.verifier_v3 import verify_evidence
from engineering_evidence.independence_v3 import independence

def assess(candidate,evidence,*,workspace,revision,task_id,state_dir=None,confidence=0.0):
 path=candidate.get('path') if isinstance(candidate,dict) else str(candidate);valid=[];errs=[]
 for e in evidence:
  ok,reason=verify_evidence(e,workspace=workspace,revision=revision,task_id=task_id,candidate=path,state_dir=state_dir)
  (valid if ok else errs).append(e if ok else reason)
 links=[e for e in valid if (e.get('task_relevance') or {}).get('task_linking') and (e.get('task_relevance') or {}).get('score',0)>=.7]
 corrobor=[]
 for e in valid:
  if e in links:continue
  if any(independence(e,l)=='INDEPENDENT' for l in links):corrobor.append(e)
 state='TARGET_CONFIRMED' if links and corrobor else 'TARGET_PROBABLE' if valid else 'TARGET_AMBIGUOUS'
 return {'state':state,'allow_edit':state=='TARGET_CONFIRMED','target':path,'evidence':valid,'invalid':errs,'confidence':confidence,'independent_evidence':2 if links and corrobor else 0}
