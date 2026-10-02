from pathlib import Path
import difflib
from transactions.durable_files import DurableFileTransaction
from .reserved_paths import validate_patch_path
from .change_receipt import sign_change_receipt as legacy_sign_change_receipt
from .change_receipt_v2 import sign_change_receipt_v2
class PatchError(RuntimeError):pass
def _prepare_replace(workspace,path,old,new,expected_count=1):
 path=validate_patch_path(path);root=Path(workspace).resolve();p=(root/path).resolve();p.relative_to(root);before=p.read_text(encoding='utf-8');count=before.count(old)
 if count!=expected_count:raise PatchError(f'expected {expected_count} match, found {count}')
 after=before.replace(old,new,expected_count);diff=''.join(difflib.unified_diff(before.splitlines(True),after.splitlines(True),fromfile=path,tofile=path));return before,after,diff
def targeted_replace(workspace,path,old,new,*,expected_count=1,mode='SIMULATION'):
 before,after,diff=_prepare_replace(workspace,path,old,new,expected_count);root=Path(workspace).resolve();p=root/path
 if mode in {'TEST','SIMULATION'}:p.write_text(after,encoding='utf-8');transactional=False
 else:
  tx=DurableFileTransaction(root);tx.begin([{'path':path,'data':after}]);tx.commit();transactional=True
 return {'operation':'MODIFY','path':path,'before':before,'after':after,'diff':diff,'transactional':transactional,'lines_changed':max(0,sum(1 for x in diff.splitlines() if x.startswith('+') or x.startswith('-'))-2)}
def apply_change_plan(workspace,operations,*,mode='PRODUCTION',crash_after=None,receipt_context=None):
 root=Path(workspace).resolve();prepared=[];changes=[]
 for op in operations:
  if op.get('operation','MODIFY')!='MODIFY' or 'old' not in op or 'new' not in op:raise PatchError('unsupported or incomplete operation')
  before,after,diff=_prepare_replace(root,op['path'],op['old'],op['new'],op.get('expected_count',1));prepared.append({'path':op['path'],'data':after});changes.append({'path':op['path'],'before':before,'after':after,'diff':diff})
 if mode in {'TEST','SIMULATION'}:
  for c in changes:(root/c['path']).write_text(c['after'],encoding='utf-8')
  return {'status':'PASS','transactional':False,'changes':changes,'change_receipt':None}
 tx=DurableFileTransaction(root);tx.begin(prepared)
 try:tx.commit(crash_after=crash_after)
 except Exception:
  DurableFileTransaction.recover_all(root);return {'status':'ROLLED_BACK','transactional':True,'txid':tx.txid,'changes':changes,'change_receipt':None}
 ctx=receipt_context or {}
 if all(k in ctx for k in ('plan','root_cause_record','target_evidence','autonomy_decision','execution_policy')):
  receipt=sign_change_receipt_v2(root,task_id=ctx.get('task_id','task'),session_id=ctx.get('session_id','session'),transaction_id=tx.txid,changes=changes,plan=ctx['plan'],root_cause_record=ctx['root_cause_record'],target_evidence=ctx['target_evidence'],autonomy_decision=ctx['autonomy_decision'],execution_policy=ctx['execution_policy'],state_dir=ctx.get('state_dir'),revision_before=ctx.get('revision_before','UNKNOWN'))
 else:
  receipt=legacy_sign_change_receipt(root,task_id=ctx.get('task_id','task'),session_id=ctx.get('session_id','session'),plan_id=ctx.get('plan_id','plan'),root_cause_id=ctx.get('root_cause_id'),target_evidence_ids=ctx.get('target_evidence_ids',[]),transaction_id=tx.txid,changes=changes,revision_before=ctx.get('revision_before','UNKNOWN'),revision_after=ctx.get('revision_after','UNKNOWN'),state_dir=ctx.get('state_dir'))
 return {'status':'PASS','transactional':True,'txid':tx.txid,'changes':changes,'change_receipt':receipt}
def syntax_sanity(text):
 pairs={'(':')','[':']','{':'}'};stack=[]
 for ch in text:
  if ch in pairs:stack.append(ch)
  elif ch in pairs.values():
   if not stack or pairs[stack.pop()]!=ch:return False
 return not stack
