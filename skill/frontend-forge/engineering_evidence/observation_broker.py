from pathlib import Path
import hashlib,importlib,json,time,uuid
from provenance.central_authority import get_central_authority
from provenance.verifier import workspace_fingerprint
from .relevance_v3 import relevance

def _load(path):
 mod,fn=path.split(':',1);return getattr(importlib.import_module(mod),fn)
def _hash(p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()
class EngineeringObservationBroker:
 def __init__(self,workspace,revision,task_id,session_id='session',repository_index=None,state_dir=None):
  self.root=Path(workspace).resolve();self.revision=str(revision);self.task_id=str(task_id);self.session_id=str(session_id);self.index=repository_index or {};self.state_dir=state_dir
  regpath=Path(__file__).with_name('collector-registry-v3.json');self.registry=json.loads(regpath.read_text());self.signer=get_central_authority(state_dir).broker('engineering-observation-broker')
 def issue(self,*a,**k):raise PermissionError('RAW_OBSERVATION_SIGNING_BLOCKED')
 def observe(self,collector_id,candidate,task_context,request=None):
  if collector_id not in self.registry:raise PermissionError('UNREGISTERED_COLLECTOR')
  reg=self.registry[collector_id];impl_path=Path(__file__).resolve().parents[1]/reg['implementation'].split(':')[0].replace('.','/')
  impl_path=impl_path.with_suffix('.py')
  if not impl_path.exists() or _hash(impl_path)!=reg['code_hash']:raise PermissionError('COLLECTOR_CODE_HASH_MISMATCH')
  p=(self.root/candidate).resolve();p.relative_to(self.root)
  if not p.exists() or not p.is_file():raise FileNotFoundError('SOURCE_ARTIFACT_NOT_FOUND')
  started=time.time();raw=_load(reg['implementation'])(self.root,candidate,task_context.get('task',''),self.index,request or {});completed=time.time()
  if raw.get('claim_type') not in reg['allowed_claim_types']:raise PermissionError('COLLECTOR_OUTPUT_CLAIM_DENIED')
  if raw.get('source_artifact')!=candidate:raise ValueError('SOURCE_ARTIFACT_BINDING_FAILURE')
  observation_id=str(uuid.uuid4());artifact_hash=_hash(p)
  receipt={'record_type':'engineering_observation','observation_id':observation_id,'collector_id':collector_id,'collector_version':reg['version'],'collector_code_hash':reg['code_hash'],
    'task_id':self.task_id,'session_id':self.session_id,'candidate':candidate,'workspace_fingerprint':workspace_fingerprint(self.root,self.revision),'revision':self.revision,
    'source_artifact':candidate,'source_artifact_hash':artifact_hash,'observation':raw.get('observation'), 'metadata':raw.get('metadata',{}),'claim_type':raw['claim_type'],
    'started_at':started,'completed_at':completed,'execution_id':'obs-'+observation_id,'capability_id':'engineering-observation','requested_capability_id':'engineering-observation'}
  sr=self.signer.sign(receipt)
  if not sr.get('ok'):raise PermissionError(sr.get('error'));obs=sr['record']
  obs=sr['record'];rel=relevance(task_context,raw);eid=str(uuid.uuid4())
  ev={'record_type':'engineering_evidence','evidence_id':eid,'evidence_type':raw['claim_type'],'claim_type':raw['claim_type'],'claim':raw['claim_type'],'candidate':candidate,
    'task_id':self.task_id,'session_id':self.session_id,'workspace_fingerprint':obs['workspace_fingerprint'],'project_revision':self.revision,'collector_id':collector_id,
    'collector_version':reg['version'],'collector_code_hash':reg['code_hash'],'source_artifact':candidate,'source_artifact_hash':artifact_hash,'observation':raw.get('observation'),
    'metadata':raw.get('metadata',{}),'producer_id':collector_id,'source_id':f'{collector_id}:{observation_id}','observation_root_id':observation_id,'derivation_parent_ids':[observation_id],
    'task_relevance':rel,'directness':1.0 if raw['claim_type'] in {'REPRODUCTION','TASK_TARGET_LINK'} else .75,'created_at':completed,'observation_receipt':obs,
    'execution_id':'ev-'+eid,'capability_id':'engineering-evidence','requested_capability_id':'engineering-evidence'}
  se=self.signer.sign(ev)
  if not se.get('ok'):raise PermissionError(se.get('error'))
  return {'observation_receipt':obs,'evidence':se['record']}
