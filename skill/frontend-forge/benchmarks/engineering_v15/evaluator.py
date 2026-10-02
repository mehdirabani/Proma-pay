from pathlib import Path
from security.process_supervisor import ProcessSupervisor
from security.sandbox_policy import SandboxPolicy

def validate_command(cmd):
    if not isinstance(cmd,dict) or not isinstance(cmd.get('program'),str) or not isinstance(cmd.get('args',[]),list):raise ValueError('structured command required')
    if any(x in cmd['program'] for x in [';','&&','|']):raise ValueError('shell syntax forbidden')
    return True

def run_evaluation(workspace,commands,hidden_assertions=None):
    policy=SandboxPolicy(str(Path(workspace).resolve()),trust_level='PROJECT_TRUSTED',require_full_isolation=False)
    results=[]
    for cmd in commands:
        validate_command(cmd)
        r=ProcessSupervisor().run([cmd['program'],*cmd.get('args',[])],policy,timeout=cmd.get('timeout',60),mode='LOCAL',actor='engineering-evaluator')
        results.append({'command':cmd,'status':r['status'],'exit_code':r.get('exit_code')})
    assertions=[]
    for a in hidden_assertions or []:
        p=Path(workspace)/a['path'];text=p.read_text(errors='ignore') if p.exists() else ''
        ok=(a.get('contains') in text) if 'contains' in a else (a.get('not_contains') not in text)
        assertions.append({'id':a.get('id'),'pass':ok})
    success=all(x['status']=='PASS' and x.get('exit_code') in (0,None) for x in results) and all(a['pass'] for a in assertions)
    return {'engineering_success':success,'commands':results,'hidden_assertions':assertions}
