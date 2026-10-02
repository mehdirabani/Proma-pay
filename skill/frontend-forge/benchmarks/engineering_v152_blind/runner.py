from pathlib import Path
import json,tempfile,os
from .execution import execute_public_task
from .evaluator import evaluate
ROOT=Path(__file__).resolve().parents[2]
PUBLIC=json.loads((ROOT/'benchmarks/engineering_v152_blind/data/public_tasks.json').read_text())
SEALED_PATH=ROOT/'benchmarks/engineering_v152_blind/data/sealed_evaluation.json'
def run():
    execution_rows=[];workspaces=[]
    for t in PUBLIC:
        td=tempfile.TemporaryDirectory();workspaces.append(td);w=Path(td.name)
        (w/t['fixture_file']).write_text(t['fixture_content'])
        state=w/'.state';os.environ['FFX_RUNTIME_STATE_HOME']=str(state)
        r=execute_public_task({'task_id':t['task_id'],'task':t['task']},w,state_dir=str(state))
        execution_rows.append({'task_id':t['task_id'],'workspace':str(w),'execution':r})
    # Evaluation boundary starts only after all execution results exist.
    sealed=json.loads(SEALED_PATH.read_text())
    rows=[]
    for x in execution_rows:
        ev=evaluate(x['workspace'],sealed[x['task_id']],x['execution']) if x['execution'].get('status')=='PATCH_APPLIED' else {'success':False}
        rows.append({'task_id':x['task_id'],'execution_status':x['execution'].get('status'),'discovered_target':x['execution'].get('discovered_target'),'evaluation':ev})
    result={'measurement':'VERIFIED_BLIND_E2E_FIXTURE','tasks':len(rows),'solved':sum(1 for x in rows if x['evaluation'].get('success')),
            'rows':rows,'failure_distribution':{}}
    for x in rows:
        k='PASS' if x['evaluation'].get('success') else x['execution_status'];result['failure_distribution'][k]=result['failure_distribution'].get(k,0)+1
    result['success_rate']=result['solved']/max(1,result['tasks'])
    for td in workspaces:td.cleanup()
    return result
if __name__=='__main__':print(json.dumps(run(),indent=2))
