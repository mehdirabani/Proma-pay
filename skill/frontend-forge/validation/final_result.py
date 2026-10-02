from provenance.verifier import verify_signed
from implementation.change_receipt_verifier import verify_change_receipt

def finalize(workspace,change_receipt,validation_receipts,acceptance_results,*,task_id,session_id,state_dir=None):
 ok,reason=verify_change_receipt(change_receipt,workspace,task_id=task_id,session_id=session_id,state_dir=state_dir)
 if not ok:return {'final_status':'FAIL','reason':reason}
 vals=[]
 for r in validation_receipts or []:
  if not verify_signed(r,state_dir=state_dir) or r.get('record_type')!='engineering_validation' or r.get('change_receipt_id')!=change_receipt.get('change_receipt_id'):
   return {'final_status':'FAIL','reason':'INVALID_VALIDATION_RECEIPT'}
  vals.append(r.get('status'))
 if any(x=='FAIL' for x in vals):return {'final_status':'FAIL','reason':'VALIDATION_FAILED'}
 if any(x=='PARTIALLY_VERIFIED' for x in vals):return {'final_status':'PARTIALLY_VERIFIED','reason':'VALIDATION_PARTIAL'}
 if acceptance_results and not all(x.get('status')=='PASS' and x.get('evidence') for x in acceptance_results):return {'final_status':'PARTIALLY_VERIFIED','reason':'ACCEPTANCE_INCOMPLETE'}
 return {'final_status':'PASS','reason':'VERIFIED_CHAIN'}
