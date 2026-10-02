from pathlib import Path
import secrets,time,uuid,hashlib,json,os,shutil
from security.process_supervisor import ProcessSupervisor
from security.tool_identity import identify_tool
from .signer_client import SignerClient
from .verifier import workspace_fingerprint
import hashlib
EXPECTED_EXECUTABLE={'git':'git','typescript':'tsc','eslint':'eslint','build':'npm','lighthouse':'lighthouse','playwright':'python','axe':'python','process':'python'}
def _same_tool(actual,expected):
 a=Path(shutil.which(actual) or actual).name.lower();e=Path(shutil.which(expected) or expected).name.lower();return a==e or (e.startswith('python') and a.startswith('python'))
def _h(x):return hashlib.sha256(json.dumps(x,sort_keys=True,separators=(",",":")).encode()).hexdigest()

class _AuthorityProxy:
 def __init__(self,broker): self.broker=broker
 def _sign(self,payload,kind):
  r=dict(payload);r.setdefault('execution_id',kind+'-'+str(uuid.uuid4()));r.setdefault('capability_id',kind);r.setdefault('requested_capability_id',r['capability_id']);r['record_type']=kind
  out=self.broker.signer.sign(r)
  if not out.get('ok'): raise PermissionError(out.get('error'))
  return out['record']
 def sign_receipt(self,payload): return self._sign(payload,'receipt')
 def sign_baseline(self,payload): return self._sign(payload,'baseline')
 def sign_attestation(self,payload): return self._sign(payload,'attestation')
 def sign_state(self,payload): return self._sign(payload,'state')
 def sign_checkpoint(self,payload): return self._sign(payload,'checkpoint')
 def close(self): pass

class ExecutionBroker:
 def __init__(self,state_dir=None,supervisor=None):
  self.state_dir=state_dir or os.environ.get('FFX_RUNTIME_STATE_HOME',str(Path.home()/'.frontend-forge-x'));self.supervisor=supervisor or ProcessSupervisor();self.broker_id='broker-'+secrets.token_hex(8);self.secret=secrets.token_bytes(32);self.signer=SignerClient(self.state_dir,self.broker_id,self.secret);self._authority=_AuthorityProxy(self)
 def execute(self,*,session_id,task_id,capability_id,adapter_id,actor,argv,policy,runtime_mode='LOCAL',project_revision='UNKNOWN',timeout=None,env=None,cancellation_token=None,execution_id=None):
  expected=EXPECTED_EXECUTABLE.get(adapter_id)
  if not expected or not argv or not _same_tool(argv[0],expected):return {'status':'SECURITY_BLOCKED','error':'TOOL_IDENTITY_MISMATCH','execution_id':execution_id or str(uuid.uuid4())}
  eid=execution_id or str(uuid.uuid4());start=time.time();ident=identify_tool(argv[0]);res=self.supervisor.run(argv,policy,timeout=timeout,mode=runtime_mode,env=env,cancellation_token=cancellation_token,execution_id=eid,actor=actor,issue_receipt=False,session_id=session_id,capability_id=capability_id);end=time.time()
  rec={'receipt_id':str(uuid.uuid4()),'session_id':session_id,'task_id':task_id,'execution_id':eid,'capability_id':capability_id,'requested_capability_id':capability_id,'adapter_id':adapter_id,'actor':actor,'runtime_mode':runtime_mode,'workspace_fingerprint':workspace_fingerprint(policy.workspace(),project_revision),'project_revision':project_revision,'command_hash':_h(argv),'tool_binary_hash':ident.get('binary_hash'),'tool_version':ident.get('version'),'tool_path':ident.get('resolved_path'),'sandbox_backend':res.get('sandbox_backend'),'sandbox_policy_hash':_h(policy.__dict__),'started_at':start,'completed_at':end,'exit_code':res.get('exit_code'),'stdout_hash':hashlib.sha256((res.get('stdout') or '').encode()).hexdigest(),'stderr_hash':hashlib.sha256((res.get('stderr') or '').encode()).hexdigest(),'status':res.get('status'),'broker_id':self.broker_id}
  signed=self.signer.sign(rec)
  if not signed.get('ok'):raise PermissionError(signed.get('error'))
  res['execution_receipt']=signed['record'];return res
 def attest_artifact(self,receipt,artifact_path,logical_path=None,source_kind='file'):
  if receipt.get('status')!='PASS':raise ValueError('cannot attest artifact from non-PASS execution')
  p=Path(artifact_path).resolve();h=hashlib.sha256(p.read_bytes()).hexdigest()
  if source_kind=='stdout' and h!=receipt.get('stdout_hash'):raise ValueError('artifact not derived from execution stdout')
  payload={'attestation_id':str(uuid.uuid4()),'receipt_id':receipt['receipt_id'],'session_id':receipt['session_id'],'task_id':receipt['task_id'],'project_revision':receipt['project_revision'],'workspace_fingerprint':receipt['workspace_fingerprint'],'logical_path':logical_path or p.name,'artifact_hash':h,'created_at':time.time(),'source_kind':source_kind}
  return self._authority.sign_attestation(payload)
 def close(self):self.signer.close()
