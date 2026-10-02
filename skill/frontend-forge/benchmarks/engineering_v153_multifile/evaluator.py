from pathlib import Path
from implementation.change_receipt_verifier import verify_change_receipt
from provenance.verifier import verify_signed

def evaluate(row,sealed,state_dir):
 if row.get('status')!='PATCH_APPLIED':return {'success':False,'stage':row.get('status')}
 truth=sealed[row['task_id']];target=truth['target'];w=Path(row['workspace']);p=w/target
 target_ok=row.get('candidate')==target;behavior=p.exists() and all(x in p.read_text() for x in truth['must_contain']) and all(x not in p.read_text() for x in truth['must_not_contain'])
 patch=row.get('patch') or {};receipt=patch.get('change_receipt');receipt_ok=False;reason='NO_RECEIPT'
 if receipt:
  receipt_ok,reason=verify_change_receipt(receipt,w,task_id=row['task_id'],session_id='blind',transaction_id=patch.get('txid'),state_dir=state_dir)
 vr=row.get('validation_receipt');validation_ok=bool(vr and verify_signed(vr,state_dir=state_dir) and vr.get('change_receipt_id')==(receipt or {}).get('change_receipt_id') and vr.get('status')=='PASS')
 return {'success':bool(target_ok and behavior and receipt_ok and validation_ok),'target_ok':target_ok,'behavior_ok':behavior,'change_receipt_ok':receipt_ok,'validation_ok':validation_ok,'receipt_reason':reason}
