from pathlib import Path
import json,time,uuid
from provenance.central_authority import get_central_authority
from implementation.patch_engine import syntax_sanity

def validate_change(workspace,changes,plan,change_receipt,*,task_id,session_id,state_dir=None):
 root=Path(workspace).resolve();checks=[]
 # Always-available local structural syntax sanity for changed text.
 syntax_ok=all(syntax_sanity(c.get('after','')) for c in changes)
 checks.append({'check':'syntax','status':'PASS' if syntax_ok else 'FAIL','available':True})
 pkg=root/'package.json';scripts={}
 if pkg.exists():
  try:scripts=json.loads(pkg.read_text()).get('scripts',{})
  except Exception:scripts={}
 for name in ('typecheck','test','build'):
  checks.append({'check':name,'status':'UNVERIFIED' if name in scripts else 'NOT_APPLICABLE','available':name in scripts})
 available_required=[x for x in checks if x['available']]
 if any(x['status']=='FAIL' for x in available_required):overall='FAIL'
 elif any(x['status'] in {'UNVERIFIED','TOOL_UNAVAILABLE','TOOL_TIMEOUT'} for x in available_required):overall='PARTIALLY_VERIFIED'
 else:overall='PASS'
 record={'record_type':'engineering_validation','validation_receipt_id':str(uuid.uuid4()),'task_id':task_id,'session_id':session_id,'change_receipt_id':change_receipt.get('change_receipt_id'),
   'checks':checks,'status':overall,'created_at':time.time(),'execution_id':'validation-'+str(uuid.uuid4()),'capability_id':'engineering-validation','requested_capability_id':'engineering-validation'}
 r=get_central_authority(state_dir).broker('validation-broker').sign(record)
 if not r.get('ok'):raise PermissionError(r.get('error'))
 return r['record']
