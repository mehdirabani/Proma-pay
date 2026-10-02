def change_budget(task_model):
    risk=task_model.get('risk','MEDIUM')
    if risk=='LOW':return {'max_files':2,'max_lines':40,'architecture_changes':False,'dependency_changes':False}
    if risk=='MEDIUM':return {'max_files':5,'max_lines':150,'architecture_changes':False,'dependency_changes':False}
    return {'max_files':10,'max_lines':400,'architecture_changes':task_model.get('operation') in {'MIGRATE','REFACTOR'},'dependency_changes':False}
def plan_change(task_model,root_cause,target_safety,impact=None):
    base={'goal':task_model.get('goal'),'files':[],'operations':[],'dependencies':[],'expected_effect':[], 'risks':[],
          'validation':[],'rollback':[],'change_budget':change_budget(task_model)}
    if target_safety.get('state')!='TARGET_CONFIRMED':return {**base,'status':'BLOCKED','reason':'target not confirmed','risks':['wrong target']}
    repair_semantic = task_model.get('operation')=='REPAIR' or (task_model.get('operation') is None and task_model.get('task_type') in {'BUG_FIX','RESPONSIVE_FIX','ACCESSIBILITY_FIX','PERFORMANCE_FIX','TYPE_ERROR','BUILD_FAILURE','TEST_FAILURE'})
    if repair_semantic and root_cause.get('status')!='ROOT_CAUSE_CONFIRMED':
        return {**base,'status':'DIAGNOSTIC_PLAN_ONLY','reason':'root cause unverified','validation':['collect reproduction/causal evidence']}
    target=target_safety.get('target') or (task_model.get('targets') or [None])[0]
    if not target:return {**base,'status':'BLOCKED','reason':'missing target'}
    cause=root_cause.get('root_cause') or {};cause_id=cause.get('hypothesis_id');cause_text=cause.get('cause') or cause.get('hypothesis') or 'explicit requested change'
    validation=['syntax','typecheck','tests','build']
    op={'operation':'MODIFY','path':target,'symbol':None,'reason':f'address evidence-backed cause: {cause_text}',
        'root_cause_id':cause_id,'expected_effect':'; '.join(task_model.get('acceptance_criteria',[])),'validation':validation}
    return {**base,'status':'PASS','reason':'evidence-grounded plan','files':[target],'operations':[op],
      'dependencies':(impact or {}).get('direct',[]),'expected_effect':task_model.get('acceptance_criteria',[]),
      'risks':[] if task_model.get('risk')=='LOW' else ['behavior regression'],'validation':validation,'rollback':['restore durable checkpoint']}
