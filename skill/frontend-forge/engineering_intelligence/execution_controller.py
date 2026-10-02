from implementation.patch_engine import apply_change_plan
ALLOWED={'L3_IMPLEMENT_LOW_RISK','L4_IMPLEMENT_AND_VALIDATE','L5_AUTONOMOUS_REPAIR'}
class ExecutionPolicyError(PermissionError):pass

def authorize(plan,autonomy,task_model,root_cause,validation_plan=None):
 if plan.get('status')!='PASS':return {'authorized':False,'status':'EXECUTION_POLICY_BLOCKED','reason':'plan not executable'}
 if autonomy.get('level') not in ALLOWED:return {'authorized':False,'status':'EXECUTION_POLICY_BLOCKED','reason':'autonomy level cannot patch'}
 if task_model.get('operation')=='REPAIR' and root_cause.get('status')!='ROOT_CAUSE_CONFIRMED':return {'authorized':False,'status':'EXECUTION_POLICY_BLOCKED','reason':'repair root cause not confirmed'}
 return {'authorized':True,'status':'PASS','policy_hash_input':{'plan_status':plan.get('status'),'autonomy':autonomy.get('level'),'operation':task_model.get('operation'),'root_cause':root_cause.get('status')}}
class EngineeringExecutionController:
 def execute(self,workspace,operations,*,plan,autonomy,task_model,root_cause,receipt_context,mode='PRODUCTION',validation_plan=None):
  decision=authorize(plan,autonomy,task_model,root_cause,validation_plan)
  if not decision['authorized']:return {**decision,'patch_applied':False}
  ctx={**receipt_context,'plan':plan,'root_cause_record':root_cause,'autonomy_decision':autonomy,'execution_policy':decision}
  r=apply_change_plan(workspace,operations,mode=mode,receipt_context=ctx);return {**r,'execution_policy':decision,'patch_applied':r.get('status')=='PASS'}
