from .cli_adapter import CLIAdapter
from .base_adapter import AdapterResult
import shutil,importlib.util,os,json,uuid
from pathlib import Path
from security.path_policy import resolve_in_workspace
from security.package_manager_policy import execution_plan,validate_plan,PackagePolicyError
from security.url_policy import validate_url,URLPolicyError
from provenance.execution_broker import ExecutionBroker
from evidence.evidence_store import create_tool_evidence
from browser.browser_runtime import BrowserRuntime

class GitAdapter(CLIAdapter):
    name='git';executable='git'
    def build_command(self,c):return ['git','status','--porcelain']
class TypeScriptAdapter(CLIAdapter):
    name='typescript';executable='tsc';require_full_isolation=True
    def build_command(self,c):return ['tsc','--noEmit','--pretty','false']
class ESLintAdapter(CLIAdapter):
    name='eslint';executable='eslint';require_full_isolation=True
    def build_command(self,c):return ['eslint','.','-f','json']
class BuildAdapter(CLIAdapter):
    name='build';executable='npm';require_full_isolation=True;SAFE_SCRIPTS={'build','test','lint','typecheck'}
    def build_command(self,c):
        script=c.get('script','build')
        if script not in self.SAFE_SCRIPTS:raise ValueError('script not allowed')
        plan=execution_plan(c['workspace'],script,c.get('package_manager','npm'));validate_plan(plan,untrusted=c.get('trust_level','PROJECT_UNTRUSTED') not in {'trusted','PROJECT_TRUSTED','RUNTIME_TRUSTED'})
        return [c.get('package_manager','npm'),'run','--if-present',script]
class LighthouseAdapter(CLIAdapter):
    name='lighthouse';executable='lighthouse';require_full_isolation=True
    def build_command(self,c):
        validate_url(c['target_url'],allow_private=bool(c.get('allow_private_url',False)))
        return ['lighthouse',c['target_url'],'--output=json','--output-path=stdout','--quiet','--chrome-flags=--headless']
    def execute(self,context,mode='LOCAL',timeout=120):
        if 'workspace' not in context:return AdapterResult('INVALID_CAPABILITY_OUTPUT',self.name,error='workspace required').to_dict()
        r=super().execute(context,mode,timeout)
        if r['status']=='PASS':
            try:
                workspace=resolve_in_workspace(context['workspace'],'.');rel=f'.ffx/artifacts/lighthouse-{uuid.uuid4().hex}.json';p=resolve_in_workspace(workspace,rel);p.parent.mkdir(parents=True,exist_ok=True);p.write_text(r['stdout'],encoding='utf-8')
                receipt=(r.get('evidence') or {}).get('execution_receipt')
                b=ExecutionBroker()
                try:att=b.attest_artifact(receipt,p,source_kind='stdout')
                finally:b.close()
                common=dict(workspace=workspace,artifact_attestation=att,execution_receipt=receipt,session_id=context.get('session_id','local-session'),task_id=context.get('task_id','local-task'),project_revision=context.get('project_revision','UNKNOWN'),capability_id=context.get('capability_id','lighthouse-check'))
                r['evidence']={m:create_tool_evidence(m,'lighthouse',receipt.get('tool_version') or 'unknown',p,**common) for m in ('performance','accessibility','seo')}
            except Exception as e:r['status']='INVALID_CAPABILITY_OUTPUT';r['error']=str(e);r['evidence']=None
        return r
class PlaywrightAdapter:
    name='playwright'
    def available(self):return BrowserRuntime('.').availability()['status']=='AVAILABLE'
    def execute(self,context,mode='LOCAL',timeout=120):
        if not context.get('workspace') or not context.get('target_url'):return AdapterResult('INVALID_CAPABILITY_OUTPUT',self.name,error='workspace and target_url required').to_dict()
        try:r=BrowserRuntime(context['workspace']).inspect(context['target_url'],viewport=context.get('viewport'),timeout_ms=int(timeout*1000),interactions=context.get('interactions'),screenshot_path=context.get('screenshot_path'))
        except Exception as e:return AdapterResult('SECURITY_BLOCKED',self.name,error=str(e)).to_dict()
        r['adapter']=self.name;return r
class AxeAdapter:
    name='axe'
    def available(self):return False
    def execute(self,context,mode='LOCAL',timeout=60):return AdapterResult('UNIMPLEMENTED',self.name,error='Playwright+axe bridge requires CI conformance implementation').to_dict()
