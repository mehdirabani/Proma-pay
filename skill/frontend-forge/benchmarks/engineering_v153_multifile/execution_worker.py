from pathlib import Path
import json,os,sys,tempfile,traceback
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
from engineering_intelligence.task_analyzer_v3 import analyze_task_v3
from engineering_evidence.observation_broker import EngineeringObservationBroker
from engineering_intelligence.target_safety_v3 import assess
from debugging.root_cause_v3 import diagnose
from planning.change_planner import plan_change
from planning.plan_attestation import attest_plan
from impact.impact_predictor import predict
from engineering_evidence.quality_v3 import evidence_quality
from engineering_intelligence.autonomy_v3 import decide
from implementation.causal_patch import generate,validate_causal_patch
from engineering_intelligence.execution_controller import EngineeringExecutionController
from validation.engineering_validation import validate_change

def attack_probe(path):
 out={}
 for op in ('read','list','stat'):
  try:
   if op=='read':Path(path).read_text()
   elif op=='list':list(Path(path).parent.iterdir())
   else:Path(path).stat()
   out[op]='ACCESSIBLE'
  except Exception:out[op]='ACCESS_DENIED'
 return out

def execute_case(case,base,state_dir):
 w=Path(base)/case['task_id'];w.mkdir(parents=True,exist_ok=True)
 for rel,content in case['files'].items():p=w/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(content)
 idx=RepositoryIndexer(w).scan();disc=TargetDiscoveryEngine(idx).discover(case['task'])
 if not disc.get('targets'):return {'task_id':case['task_id'],'status':'RETRIEVAL_FAILURE'}
 cand=disc['targets'][0];candidate=cand['path'];tm=analyze_task_v3(case['task']);broker=EngineeringObservationBroker(w,'fixture-r1',case['task_id'],'blind',idx,state_dir=state_dir)
 try:
  target=[]
  for cid in ('static-task-link-collector','dependency-graph-collector','repository-symbol-collector'):
   target.append(broker.observe(cid,candidate,{'task':case['task'],'domains':tm['domains']})['evidence'])
  safe=assess(cand,target,workspace=w,revision='fixture-r1',task_id=case['task_id'],state_dir=state_dir,confidence=disc.get('confidence',0))
  if safe['state']!='TARGET_CONFIRMED':return {'task_id':case['task_id'],'status':'TARGET_VALIDATION_FAILURE','candidate':candidate,'target_state':safe['state']}
  static=broker.observe('static-analysis-collector',candidate,{'task':case['task'],'domains':tm['domains']})['evidence'];findings=(static.get('metadata') or {}).get('findings',[])
  if not findings:return {'task_id':case['task_id'],'status':'DIAGNOSIS_FAILURE','candidate':candidate}
  # choose a finding whose domain is requested by task, then actively reproduce it
  domains=set(tm['domains']);f=next((x for x in findings if x.get('domain') in domains),findings[0]);rep=broker.observe('static-reproduction-collector',candidate,{'task':case['task'],'domains':tm['domains']},request={'cause_code':f['cause_code']})['evidence']
  rc=diagnose(case['task'],candidate,[static,rep],workspace=w,revision='fixture-r1',task_id=case['task_id'],state_dir=state_dir)
  if rc['status']!='ROOT_CAUSE_CONFIRMED':return {'task_id':case['task_id'],'status':'DIAGNOSIS_FAILURE','candidate':candidate,'cause_status':rc['status']}
  impact=predict([candidate],idx['nodes']);plan=attest_plan(plan_change(tm,rc,safe,impact))
  eq=evidence_quality(target,w,'fixture-r1');auto=decide(tm,safe,rc,impact,eq['score'],.8,True,'FULL_COMPILER')
  text=(w/candidate).read_text();op=generate({'task':tm,'target':candidate,'root_cause':rc,'change_plan':plan,'evidence':[static,rep]},text)
  if validate_causal_patch(op,rc)['status']!='PASS':return {'task_id':case['task_id'],'status':'PATCH_GENERATION_FAILURE','candidate':candidate}
  result=EngineeringExecutionController().execute(w,[op],plan=plan,autonomy=auto,task_model=tm,root_cause=rc,receipt_context={'task_id':case['task_id'],'session_id':'blind','plan_id':'plan','target_evidence':target,'state_dir':state_dir,'revision_before':'fixture-r1'},mode='PRODUCTION')
  
  if result.get('patch_applied'):
   vr=validate_change(w,result.get('changes',[]),plan,result.get('change_receipt') or {},task_id=case['task_id'],session_id='blind',state_dir=state_dir)
  else: vr=None
  return {'task_id':case['task_id'],'status':'PATCH_APPLIED' if result.get('patch_applied') else 'POLICY_BLOCK','candidate':candidate,'cause_code':(rc.get('root_cause') or {}).get('cause_code'),'plan_status':plan.get('status'),'plan_hash':plan.get('plan_hash'),'autonomy':auto.get('level'),'workspace':str(w),'patch':result,'validation_receipt':vr}
 finally:pass

def main():
 public=json.loads(Path(sys.argv[1]).read_text());base=sys.argv[2];outpath=sys.argv[3];state_dir=sys.argv[4];forbidden=os.environ.get('FFX_FORBIDDEN_SEALED_PATH');rows=[]
 probe=attack_probe(forbidden) if forbidden else {}
 for c in public:
  try:rows.append(execute_case(c,base,state_dir))
  except Exception as e:rows.append({'task_id':c['task_id'],'status':'EXECUTION_EXCEPTION','error':repr(e),'trace':traceback.format_exc(limit=2)})
 Path(outpath).write_text(json.dumps({'rows':rows,'sealed_probe':probe},indent=2))
if __name__=='__main__':main()
