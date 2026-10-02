import hashlib,json,time,uuid
from pathlib import Path
from provenance.central_authority import get_central_authority
from provenance.verifier import workspace_fingerprint

def h(x):return hashlib.sha256(json.dumps(x,sort_keys=True,separators=(",",":"),ensure_ascii=False,default=str).encode()).hexdigest()
def workspace_content_fingerprint(root):
 root=Path(root).resolve();parts=[]
 for p in sorted(root.rglob('*')):
  if p.is_file() and '.ffx-transactions' not in p.parts and '.state' not in p.parts:
   try:parts.append(str(p.relative_to(root))+':'+hashlib.sha256(p.read_bytes()).hexdigest())
   except Exception:pass
 return hashlib.sha256('\n'.join(parts).encode()).hexdigest()
def sign_change_receipt_v2(workspace,*,task_id,session_id,transaction_id,changes,plan,root_cause_record,target_evidence,autonomy_decision,execution_policy,state_dir=None,revision_before='UNKNOWN'):
 root=Path(workspace).resolve();plan_hash=plan.get('plan_hash') or h(plan);receipt={'record_type':'engineering_change','change_receipt_id':str(uuid.uuid4()),'task_id':task_id,'session_id':session_id,'transaction_id':transaction_id,
  'plan_id':plan.get('plan_id','plan'),'plan_hash':plan_hash,'root_cause_id':(root_cause_record.get('root_cause') or {}).get('hypothesis_id') or root_cause_record.get('root_cause_id'),
  'root_cause_record_hash':h(root_cause_record),'target_evidence_ids':[e.get('evidence_id') for e in target_evidence if e.get('evidence_id')],
  'target_evidence_hash':h([e.get('evidence_id') for e in target_evidence if e.get('evidence_id')]),'autonomy_decision_hash':h(autonomy_decision),'execution_policy_hash':h(execution_policy),
  'files_changed':[c['path'] for c in changes],'before_hashes':{c['path']:hashlib.sha256(c['before'].encode()).hexdigest() for c in changes},'after_hashes':{c['path']:hashlib.sha256(c['after'].encode()).hexdigest() for c in changes},
  'project_revision_before':revision_before,'project_revision_after':workspace_content_fingerprint(root),'created_at':time.time(),'execution_id':'change-'+str(uuid.uuid4()),'capability_id':'engineering-change','requested_capability_id':'engineering-change'}
 r=get_central_authority(state_dir).broker('change-broker').sign(receipt)
 if not r.get('ok'):raise PermissionError(r.get('error'))
 return r['record']
