from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
from engineering_intelligence.orchestrator import analyze_engineering_task
from implementation.patch_engine import apply_change_plan
from .repair_engine import derive_patch

def execute_public_task(public_task,workspace,*,revision='fixture-r1',state_dir=None):
    index=RepositoryIndexer(workspace).scan()
    discovery=TargetDiscoveryEngine(index).discover(public_task['task'])
    targets=discovery.get('targets') or []
    if not targets:return {'status':'RETRIEVAL_FAILURE','retrieval':discovery}
    candidate={'path':targets[0]['path'],'confidence':max(.65,float(discovery.get('calibrated_confidence',0)))}
    result=analyze_engineering_task(public_task['task'],workspace,candidate=candidate,retrieval_status='TARGET_RESOLVED',repository_index=index,
        revision=revision,task_id=public_task['task_id'],session_id='blind',mode='PRODUCTION')
    if result['target_safety']['state']!='TARGET_CONFIRMED':return {'status':'TARGET_VALIDATION_FAILURE','retrieval':discovery,'analysis':result}
    if result['engineering_task']['operation']=='REPAIR' and result['root_cause']['status'] not in {'ROOT_CAUSE_CONFIRMED','ROOT_CAUSE_PROBABLE'}:
        return {'status':'DIAGNOSIS_FAILURE','retrieval':discovery,'analysis':result}
    path=candidate['path'];p=Path(workspace)/path;text=p.read_text()
    patch=derive_patch(public_task['task'],path,text,result['root_cause'])
    if not patch:return {'status':'PATCH_GENERATION_FAILURE','retrieval':discovery,'analysis':result}
    op={'operation':'MODIFY','path':path,'old':patch['old'],'new':patch['new'],'expected_count':1}
    applied=apply_change_plan(workspace,[op],mode='PRODUCTION',receipt_context={
        'task_id':public_task['task_id'],'session_id':'blind','plan_id':'blind-plan',
        'root_cause_id':(result['root_cause'].get('root_cause') or {}).get('hypothesis_id'),
        'target_evidence_ids':[e['evidence_id'] for e in result['target_evidence']],'state_dir':state_dir})
    if applied['status']!='PASS':return {'status':'PATCH_APPLICATION_FAILURE','retrieval':discovery,'analysis':result,'patch':applied}
    return {'status':'PATCH_APPLIED','discovered_target':path,'retrieval':discovery,'analysis':result,'patch':applied}
