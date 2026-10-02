R={'LOW':0,'MEDIUM':1,'HIGH':2,'CRITICAL':3}
def decide(task_model,target,root_cause,impact,evidence_quality=1.0,test_coverage=.8,validation_available=True,code_mode='FULL_COMPILER'):
 if target.get('state')!='TARGET_CONFIRMED':return {'level':'L1_SUGGEST','reason':'target not confirmed'}
 risk=max([task_model.get('risk','MEDIUM'),impact.get('risk','LOW'),'HIGH' if impact.get('centrality',0)>=.65 or impact.get('public_api_impact') else 'LOW','HIGH' if evidence_quality<.55 else 'LOW','HIGH' if test_coverage<.35 else 'LOW'],key=lambda x:R.get(x,1))
 if task_model.get('operation')=='REPAIR':
  if root_cause.get('status')!='ROOT_CAUSE_CONFIRMED':return {'level':'L2_PLAN','reason':'root cause not confirmed','composite_risk':risk}
  tier=root_cause.get('causal_tier','HYPOTHESIS')
  if tier=='STATIC_ASSOCIATION':return {'level':'L2_PLAN','reason':'static association only','composite_risk':risk}
  if tier=='REPRODUCED' and risk in {'LOW','MEDIUM'}:return {'level':'L3_IMPLEMENT_LOW_RISK','reason':'reproduced cause','composite_risk':risk}
  if tier=='INTERVENTION_CONFIRMED' and risk=='LOW' and validation_available:return {'level':'L4_IMPLEMENT_AND_VALIDATE','reason':'intervention-confirmed low risk','composite_risk':risk}
 if risk in {'HIGH','CRITICAL'} or not validation_available:return {'level':'L2_PLAN','reason':'impact/validation risk','composite_risk':risk}
 return {'level':'L3_IMPLEMENT_LOW_RISK','reason':'policy-approved','composite_risk':risk}
