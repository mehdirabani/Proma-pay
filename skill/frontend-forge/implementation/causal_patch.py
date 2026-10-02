import re,hashlib,json
class CausalPatchError(ValueError):pass

def _cause(root_cause):
 rc=root_cause.get('root_cause') or root_cause;return rc.get('cause_code') or rc.get('pattern_id') or rc.get('metadata',{}).get('cause_code')
def generate(inp,text):
 task=inp.get('task') or {};rc=inp.get('root_cause') or {};plan=inp.get('change_plan') or {};target=inp.get('target');code=_cause(rc)
 if task.get('operation')=='REPAIR' and rc.get('status','ROOT_CAUSE_CONFIRMED')!='ROOT_CAUSE_CONFIRMED' and (inp.get('root_cause') or {}).get('status')!='ROOT_CAUSE_CONFIRMED':raise CausalPatchError('ROOT_CAUSE_REQUIRED')
 if plan.get('status')!='PASS':raise CausalPatchError('VALID_PLAN_REQUIRED')
 old=new=None
 if code=='MIN_WIDTH_FIXED':
  m=re.search(r'min-width\s*:\s*\d+px\s*;',text,re.I);old=m.group(0) if m else None;new='min-width: 0;'
 elif code=='NOWRAP':
  m=re.search(r'white-space\s*:\s*nowrap\s*;',text,re.I);old=m.group(0) if m else None;new='white-space: normal;'
 elif code=='FIXED_WIDTH':
  m=re.search(r'(?<!min-)width\s*:\s*(?:6\d\d|7\d\d|8\d\d)px\s*;',text,re.I);old=m.group(0) if m else None;new='width: 100%;'
 elif code=='MISSING_LABEL':
  m=re.search(r'<input(?![^>]*(?:aria-label|aria-labelledby))[^>]*>',text,re.I);old=m.group(0) if m else None;new=(old[:-1]+' aria-label="Field">') if old else None
 elif code=='BUTTON_NAME':
  m=re.search(r'<button([^>]*)>\s*<svg',text,re.I);old=m.group(0) if m else None;new=(old.replace('<button','<button aria-label="Action"',1)) if old else None
 elif code=='ROUTE_TYPO':
  m=re.search(r'href=[\'\"]/(profil|chekout|dashbord)[\'\"]',text,re.I);old=m.group(0) if m else None
  if old:
   new=old.replace('/profil','/profile').replace('/chekout','/checkout').replace('/dashbord','/dashboard')
 elif code=='TYPE_STRING_NUMBER':
  m=re.search(r'\b(price|total|count|amount)\s*:\s*string\b',text,re.I);old=m.group(0) if m else None;new=(m.group(1)+': number') if m else None
 elif code=='LOADING_NULL':old='if (loading) return null;' if 'if (loading) return null;' in text else None;new='if (loading) return <div>Loading...</div>;'
 elif code=='NON_SUBMIT':
  m=re.search(r'type=[\'\"]button[\'\"]',text,re.I);old=m.group(0) if m else None;new='type="submit"'
 elif code=='TAILWIND_FIXED_WIDTH':
  m=re.search(r'w-\[(?:6\d\d|7\d\d|8\d\d)px\]',text);old=m.group(0) if m else None;new='w-full max-w-full'
 else:raise CausalPatchError('UNSUPPORTED_CAUSAL_MECHANISM')
 if not old or old not in text:raise CausalPatchError('PATCH_CAUSE_MISMATCH')
 rcid=(rc.get('root_cause') or rc).get('hypothesis_id') or (rc.get('root_cause') or rc).get('root_cause_id') or 'cause'
 evid=[x.get('evidence_id') for x in inp.get('evidence',[]) if isinstance(x,dict) and x.get('evidence_id')]
 return {'operation':'MODIFY','path':target,'old':old,'new':new,'root_cause_id':rcid,'evidence_ids':evid,'mechanism':code,'expected_effect':plan.get('expected_effect') or task.get('acceptance_criteria',[])}
def validate_causal_patch(op,root_cause):
 code=_cause(root_cause)
 return {'status':'PASS' if op.get('mechanism')==code and op.get('root_cause_id') else 'PATCH_CAUSE_MISMATCH'}
