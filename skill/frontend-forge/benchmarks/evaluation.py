from pathlib import Path
from security.command_policy import validate_command,CommandPolicyError
from security.process_supervisor import ProcessSupervisor
from security.sandbox_policy import SandboxPolicy
class EngineeringTaskEvaluator:
    def __init__(self,supervisor=None):self.supervisor=supervisor or ProcessSupervisor()
    def _run(self,root,cmd,timeout):
        if isinstance(cmd,str):return {'pass':False,'status':'INVALID_COMMAND_CONTRACT','exit_code':None}
        argv=[cmd['program'],*cmd.get('args',[])];timeout=cmd.get('timeout',timeout)
        try:validate_command(argv)
        except CommandPolicyError:return {'pass':False,'status':'SECURITY_BLOCKED','exit_code':None}
        r=self.supervisor.run(argv,SandboxPolicy(str(root),trust_level='PROJECT_TRUSTED',require_full_isolation=False),timeout=timeout,mode='LOCAL')
        return {'pass':r.get('status')=='PASS','status':r.get('status'),'exit_code':r.get('exit_code')}
    def evaluate(self,workspace,evaluation):
        root=Path(workspace).resolve();checks={};critical=False
        for name,key in [('build','build_cmd'),('typecheck','typecheck_cmd'),('tests','test_cmd')]:
            cmd=evaluation.get(key)
            if not cmd:continue
            checks[name]=self._run(root,cmd,evaluation.get('timeout',20));critical|=not checks[name]['pass']
        assertions=[]
        for a in evaluation.get('behavior_assertions',[]):
            p=root/a['path'];ok=p.exists() and a.get('contains','') in p.read_text(errors='ignore');assertions.append({'path':a['path'],'pass':ok});critical|=not ok
        return {'engineering_task_success':not critical,'checks':checks,'behavior_assertions':assertions,'status':'PASS' if not critical else 'FAIL'}
def context_success(selected,hidden):
    s=set(selected);required=set(hidden.get('relevant_files',[]));critical=set(hidden.get('critical_files',required));tp=len(s&required)
    return {'context_success':len(s&critical)==len(critical),'context_precision':round(tp/max(1,len(s)),4),'context_recall':round(tp/max(1,len(required)),4),'critical_dependency_recall':round(len(s&critical)/max(1,len(critical)),4)}
