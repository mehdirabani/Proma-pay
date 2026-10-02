from pathlib import Path
from security.process_supervisor import ProcessSupervisor
from security.sandbox_policy import SandboxPolicy
from .evidence import create_evidence

def reproduce(workspace,checks,*,revision='UNKNOWN',task_id='task'):
    root=Path(workspace).resolve();results=[];evidence=[]
    policy=SandboxPolicy(str(root),trust_level='PROJECT_TRUSTED',require_full_isolation=False)
    for c in checks or []:
        kind=c.get('kind','static')
        if kind=='static':
            p=(root/c['path']).resolve();
            try:p.relative_to(root)
            except ValueError:results.append({'kind':'static','status':'ENVIRONMENT_BLOCKED','reason':'path escape'});continue
            text=p.read_text(errors='ignore') if p.exists() else ''
            ok=(c.get('contains','') in text) if 'contains' in c else (c.get('not_contains') not in text)
            status='REPRODUCED' if ok else 'NOT_REPRODUCED';r={'kind':'static','status':status,'check':c};results.append(r)
            if ok:evidence.append(create_evidence('REPRODUCTION','static-reproduction',str(c['path']),f"static reproduction matched: {c}",str(c['path']),revision,.95,workspace=str(root),task_id=task_id,direct=True))
        elif kind in {'build','typecheck','test'}:
            cmd=c.get('command') or {};program=cmd.get('program');args=cmd.get('args',[])
            if not program or not isinstance(args,list):results.append({'kind':kind,'status':'UNVERIFIED','reason':'invalid command contract'});continue
            pr=ProcessSupervisor().run([program,*args],policy,timeout=cmd.get('timeout',60),mode='LOCAL',actor=f'reproduction-{kind}')
            # A failing validation can reproduce a reported build/type/test failure.
            expected_failure=c.get('expect_failure',True);reproduced=(pr.get('status')!='PASS' or pr.get('exit_code') not in (0,None)) if expected_failure else pr.get('status')=='PASS'
            status='REPRODUCED' if reproduced else 'NOT_REPRODUCED';results.append({'kind':kind,'status':status,'process_status':pr.get('status'),'exit_code':pr.get('exit_code')})
            if reproduced:evidence.append(create_evidence(kind.upper() if kind!='test' else 'TEST',f'{kind}-adapter',kind,f'{kind} reproduction status {pr.get("status")}',kind,revision,.95,workspace=str(root),task_id=task_id,direct=True))
        else:results.append({'kind':kind,'status':'UNVERIFIED','reason':'tool/reproduction mode unsupported'})
    if any(r['status']=='REPRODUCED' for r in results):status='REPRODUCED'
    elif results and all(r['status']=='NOT_REPRODUCED' for r in results):status='NOT_REPRODUCED'
    elif any(r['status']=='ENVIRONMENT_BLOCKED' for r in results):status='ENVIRONMENT_BLOCKED'
    else:status='UNVERIFIED'
    return {'status':status,'results':results,'evidence':evidence}
