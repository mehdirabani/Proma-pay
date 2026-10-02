import hashlib,json,time,uuid,re
VALID_KINDS={
 'STATIC_ANALYSIS','DEPENDENCY_GRAPH','ROUTE','TEST','UI_STRING','SYMBOL','BROWSER','TYPECHECK','BUILD','GIT','REPRODUCTION','RUNTIME'
}
DIRECT_KINDS={'STATIC_ANALYSIS','BROWSER','TYPECHECK','TEST','BUILD','RUNTIME','REPRODUCTION'}
REQ={'evidence_id','kind','source','artifact','observation','target','revision','confidence','workspace','task_id','evidence_hash'}

def _canonical(x):return json.dumps(x,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()
def evidence_hash(record):return hashlib.sha256(_canonical({k:v for k,v in record.items() if k!='evidence_hash'})).hexdigest()
def create_evidence(kind,source,artifact,observation,target,revision,confidence=1.0,*,workspace='',task_id='',direct=None,metadata=None):
    if kind not in VALID_KINDS:raise ValueError('INVALID_EVIDENCE_KIND')
    r={'evidence_id':str(uuid.uuid4()),'kind':kind,'source':str(source),'artifact':str(artifact),'observation':str(observation),
       'target':str(target),'revision':str(revision),'confidence':float(confidence),'workspace':str(workspace),'task_id':str(task_id),
       'direct':bool(kind in DIRECT_KINDS if direct is None else direct),'metadata':metadata or {},'observed_at':time.time()}
    r['evidence_hash']=evidence_hash(r);return r

def validate_evidence(e,*,candidate=None,revision=None,workspace=None,task_id=None):
    if not isinstance(e,dict) or not REQ.issubset(e):return False,'INVALID_EVIDENCE_CONTRACT'
    if e.get('kind') not in VALID_KINDS:return False,'INVALID_EVIDENCE_KIND'
    if evidence_hash(e)!=e.get('evidence_hash'):return False,'EVIDENCE_HASH_MISMATCH'
    if candidate is not None and e.get('target')!=candidate:return False,'EVIDENCE_CANDIDATE_MISMATCH'
    if revision is not None and e.get('revision')!=revision:return False,'EVIDENCE_REVISION_MISMATCH'
    if workspace is not None and str(e.get('workspace'))!=str(workspace):return False,'EVIDENCE_WORKSPACE_MISMATCH'
    if task_id is not None and e.get('task_id')!=task_id:return False,'EVIDENCE_TASK_MISMATCH'
    return True,'PASS'

def normalized_terms(s):return {x for x in re.findall(r'[a-z0-9\u0600-\u06ff]+',str(s).lower()) if len(x)>2}
def independent_key(e):return (e.get('source'),e.get('kind'),hashlib.sha256(str(e.get('observation')).encode()).hexdigest()[:12])
