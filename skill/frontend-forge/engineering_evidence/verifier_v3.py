from pathlib import Path
import hashlib,time
from provenance.verifier import verify_signed,workspace_fingerprint

def verify_observation(o,*,workspace,revision,task_id,candidate,state_dir=None):
 if not isinstance(o,dict) or o.get('record_type')!='engineering_observation':return False,'INVALID_OBSERVATION_RECEIPT'
 if not verify_signed(o,state_dir=state_dir):return False,'INVALID_OBSERVATION_SIGNATURE'
 if o.get('task_id')!=str(task_id) or o.get('candidate')!=str(candidate) or o.get('revision')!=str(revision):return False,'OBSERVATION_BINDING_MISMATCH'
 if o.get('workspace_fingerprint')!=workspace_fingerprint(workspace,revision):return False,'OBSERVATION_WORKSPACE_MISMATCH'
 p=(Path(workspace)/o.get('source_artifact','')).resolve()
 try:p.relative_to(Path(workspace).resolve())
 except Exception:return False,'OBSERVATION_ARTIFACT_OUTSIDE_WORKSPACE'
 if not p.exists():return False,'SOURCE_ARTIFACT_NOT_FOUND'
 if hashlib.sha256(p.read_bytes()).hexdigest()!=o.get('source_artifact_hash'):return False,'OBSERVATION_STALE'
 return True,'PASS'

def verify_evidence(e,*,workspace,revision,task_id,candidate,state_dir=None,max_age=3600):
 if not isinstance(e,dict) or e.get('record_type')!='engineering_evidence':return False,'UNATTESTED_EVIDENCE'
 if not verify_signed(e,state_dir=state_dir):return False,'INVALID_ENGINEERING_EVIDENCE_SIGNATURE'
 if e.get('task_id')!=str(task_id) or e.get('candidate')!=str(candidate) or e.get('project_revision')!=str(revision):return False,'EVIDENCE_BINDING_MISMATCH'
 ok,reason=verify_observation(e.get('observation_receipt'),workspace=workspace,revision=revision,task_id=task_id,candidate=candidate,state_dir=state_dir)
 if not ok:return False,reason
 if e.get('observation_root_id')!=e['observation_receipt'].get('observation_id'):return False,'EVIDENCE_LINEAGE_MISMATCH'
 if max_age is not None and time.time()-float(e.get('created_at',0))>max_age:return False,'EVIDENCE_STALE'
 return True,'PASS'
