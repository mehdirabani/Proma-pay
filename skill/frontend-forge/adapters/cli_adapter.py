import shutil,os
from .base_adapter import ToolAdapter,AdapterResult
from security.command_policy import validate_command,CommandPolicyError
from security.path_policy import resolve_in_workspace
from security.sandbox_policy import SandboxPolicy
from provenance.execution_broker import ExecutionBroker

class CLIAdapter(ToolAdapter):
    executable=None;require_full_isolation=False
    def available(self):return bool(self.executable and shutil.which(self.executable))
    def build_command(self,context):raise NotImplementedError
    def execute(self,context,mode='LOCAL',timeout=60):
        if not self.available():return AdapterResult('TOOL_UNAVAILABLE',self.name,error=f'{self.executable} unavailable').to_dict()
        try:
            workspace=str(resolve_in_workspace(context['workspace'],'.'));argv=self.build_command(context);validate_command(argv)
        except (CommandPolicyError,ValueError,KeyError) as e:return AdapterResult('SECURITY_BLOCKED',self.name,error=str(e)).to_dict()
        policy=SandboxPolicy(workspace_root=workspace,network_allowed=bool(context.get('network_allowed',False)),
            trust_level=context.get('trust_level','PROJECT_UNTRUSTED'),require_full_isolation=bool(context.get('require_full_isolation',self.require_full_isolation)),
            max_processes=int(context.get('max_processes',256 if context.get('trust_level') in {'trusted','PROJECT_TRUSTED','RUNTIME_TRUSTED'} else 64)),max_memory_mb=int(context.get('max_memory_mb',1536)),
            max_cpu_seconds=int(context.get('max_cpu_seconds',max(1,int(timeout)))),default_timeout=int(timeout))
        broker=ExecutionBroker()
        try:
            r=broker.execute(session_id=context.get('session_id','local-session'),task_id=context.get('task_id','local-task'),
                capability_id=context.get('capability_id',self.name),adapter_id=self.name,actor=self.name,argv=argv,policy=policy,runtime_mode=mode,
                project_revision=context.get('project_revision','UNKNOWN'),timeout=timeout,execution_id=context.get('execution_id'),
                cancellation_token=context.get('cancellation_token'))
        finally:broker.close()
        return AdapterResult(r['status'],self.name,argv,r.get('exit_code'),r.get('stdout',''),r.get('stderr',''),
            {'kind':'cli','executable':self.executable,'execution_id':r.get('execution_id'),'sandbox_backend':r.get('sandbox_backend'),
             'isolation_level':r.get('isolation_level'),'execution_receipt':r.get('execution_receipt')},r.get('error')).to_dict()
