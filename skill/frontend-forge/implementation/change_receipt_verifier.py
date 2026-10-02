from pathlib import Path
import hashlib
from provenance.verifier import verify_signed
from .change_receipt_v2 import workspace_content_fingerprint

def verify_change_receipt(receipt,workspace,*,task_id=None,session_id=None,transaction_id=None,state_dir=None,revision_after=None):
 if not isinstance(receipt,dict) or receipt.get('record_type')!='engineering_change':return False,'INVALID_CHANGE_RECEIPT'
 if not verify_signed(receipt,state_dir=state_dir):return False,'INVALID_CHANGE_RECEIPT_SIGNATURE'
 if task_id is not None and receipt.get('task_id')!=task_id:return False,'CHANGE_TASK_MISMATCH'
 if session_id is not None and receipt.get('session_id')!=session_id:return False,'CHANGE_SESSION_MISMATCH'
 if transaction_id is not None and receipt.get('transaction_id')!=transaction_id:return False,'CHANGE_TRANSACTION_MISMATCH'
 root=Path(workspace).resolve()
 for rel,expected in receipt.get('after_hashes',{}).items():
  p=(root/rel).resolve()
  if not p.exists() or hashlib.sha256(p.read_bytes()).hexdigest()!=expected:return False,'CHANGE_AFTER_HASH_MISMATCH'
 actual=workspace_content_fingerprint(root)
 if receipt.get('project_revision_after')!=actual:return False,'CHANGE_REVISION_MISMATCH'
 if revision_after is not None and receipt.get('project_revision_after')!=revision_after:return False,'CHANGE_REVISION_MISMATCH'
 for k in ('plan_hash','root_cause_record_hash','target_evidence_hash','autonomy_decision_hash','execution_policy_hash'):
  if not receipt.get(k):return False,'CHANGE_BINDING_MISSING'
 return True,'PASS'
