from pathlib import Path
import re

def _file(workspace,candidate):
 root=Path(workspace).resolve();p=(root/candidate).resolve();p.relative_to(root)
 if not p.exists() or not p.is_file():raise FileNotFoundError('SOURCE_ARTIFACT_NOT_FOUND')
 return p

def task_link(workspace,candidate,task,index=None,request=None):
 p=_file(workspace,candidate);text=p.read_text(errors='ignore');task_tokens=set(re.findall(r'[a-z0-9\u0600-\u06ff]+',task.lower()));file_tokens=set(re.findall(r'[a-z0-9\u0600-\u06ff]+',text.lower()))
 stop={'fix','the','this','add','make','repair','resolve','برای','این','را','کن','رفع','اصلاح'};overlap=sorted(x for x in task_tokens&file_tokens if len(x)>2 and x not in stop)
 return {'claim_type':'TASK_TARGET_LINK','source_artifact':candidate,'relationship':'UI_TEXT_IN_TARGET' if overlap else 'NO_TASK_LINK','metadata':{'task_entities':overlap[:12],'source_span':None,'relationship':'UI_TEXT_IN_TARGET' if overlap else 'NO_TASK_LINK'},'observation':{'matched_entities':overlap[:12]}}

def dependency(workspace,candidate,task,index=None,request=None):
 _file(workspace,candidate);node=(index or {}).get('nodes',{}).get(candidate,{})
 rel={k:list(node.get(k) or [])[:20] for k in ('imports','reverse_imports','tests','styles','routes') if node.get(k)}
 return {'claim_type':'DEPENDENCY_RELATIONSHIP','source_artifact':candidate,'relationship':'REPOSITORY_GRAPH','metadata':{'relationships':rel,'relationship':'REPOSITORY_GRAPH'},'observation':{'relationships':rel}}

def symbol(workspace,candidate,task,index=None,request=None):
 _file(workspace,candidate);node=(index or {}).get('nodes',{}).get(candidate,{})
 syms=list(node.get('symbols') or node.get('exports') or [])[:20]
 return {'claim_type':'SYMBOL_DEFINITION','source_artifact':candidate,'relationship':'SYMBOL_IN_TARGET','metadata':{'symbols':syms,'relationship':'SYMBOL_IN_TARGET'},'observation':{'symbols':syms}}

PATTERNS=[
 ('MIN_WIDTH_FIXED',r'min-width\s*:\s*(\d+)px\s*;','responsive','minimum width exceeds narrow viewport'),
 ('NOWRAP',r'white-space\s*:\s*nowrap\s*;','responsive','nowrap prevents wrapping'),
 ('FIXED_WIDTH',r'(?<!min-)width\s*:\s*(?:6\d\d|7\d\d|8\d\d)px\s*;','responsive','fixed width exceeds narrow viewport'),
 ('MISSING_LABEL',r'<input(?![^>]*(?:aria-label|aria-labelledby))[^>]*>','accessibility','input has no accessible name'),
 ('BUTTON_NAME',r'<button([^>]*)>\s*<svg','accessibility','icon button lacks accessible name'),
 ('ROUTE_TYPO',r'href=[\'\"]/(profil|chekout|dashbord)[\'\"]','routing','route href is misspelled'),
 ('TYPE_STRING_NUMBER',r'\b(price|total|count|amount)\s*:\s*string\b','typescript','numeric prop is typed as string'),
 ('LOADING_NULL',r'if\s*\(\s*loading\s*\)\s*return\s+null\s*;','async-data','loading branch removes user feedback'),
 ('NON_SUBMIT',r'type=[\'\"]button[\'\"][^>]*>\s*(?:Submit|Pay|ارسال)','form','submit action is a non-submit button'),
 ('TAILWIND_FIXED_WIDTH',r'w-\[(?:6\d\d|7\d\d|8\d\d)px\]','responsive','fixed Tailwind width exceeds narrow viewport'),
]
def root_cause(workspace,candidate,task,index=None,request=None):
 p=_file(workspace,candidate);text=p.read_text(errors='ignore');rows=[]
 for code,pat,domain,mechanism in PATTERNS:
  m=re.search(pat,text,re.I|re.S)
  if m:rows.append({'cause_code':code,'domain':domain,'mechanism':mechanism,'match':m.group(0)[:160]})
 return {'claim_type':'ROOT_CAUSE_OBSERVATION','source_artifact':candidate,'relationship':'STATIC_CAUSAL_CANDIDATE','metadata':{'findings':rows,'relationship':'STATIC_CAUSAL_CANDIDATE'},'observation':{'findings':rows}}

def reproduce(workspace,candidate,task,index=None,request=None):
 p=_file(workspace,candidate);text=p.read_text(errors='ignore');cause=(request or {}).get('cause_code');row=next((x for x in PATTERNS if x[0]==cause),None)
 matched=bool(row and re.search(row[1],text,re.I|re.S))
 return {'claim_type':'REPRODUCTION','source_artifact':candidate,'relationship':'STATIC_REPRODUCTION','metadata':{'cause_code':cause,'reproduced':matched,'relationship':'STATIC_REPRODUCTION'},'observation':{'reproduced':matched,'cause_code':cause}}
