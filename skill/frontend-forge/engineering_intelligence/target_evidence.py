import hashlib,json,uuid,time
KINDS={'REPOSITORY_SYMBOL','DEPENDENCY_GRAPH','ROUTE','TEST','UI_STRING','STATIC_ANALYSIS','BROWSER','GIT'}
REQ={'evidence_id','kind','candidate','source','relationship','revision','workspace','task_id','observation','evidence_hash'}
def _canon(x):return json.dumps(x,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()
def calc_hash(e):return hashlib.sha256(_canon({k:v for k,v in e.items() if k!='evidence_hash'})).hexdigest()
def create_target_evidence(kind,candidate,source,relationship,revision,workspace,task_id,observation,*,metadata=None):
    if kind not in KINDS:raise ValueError('INVALID_TARGET_EVIDENCE_KIND')
    e={'evidence_id':str(uuid.uuid4()),'kind':kind,'candidate':candidate,'source':source,'relationship':relationship,'revision':revision,
       'workspace':str(workspace),'task_id':task_id,'observation':observation,'metadata':metadata or {},'observed_at':time.time()}
    e['evidence_hash']=calc_hash(e);return e
def validate_target_evidence(e,*,candidate,revision,workspace,task_id):
    if not isinstance(e,dict) or not REQ.issubset(e):return False,'INVALID_TARGET_EVIDENCE'
    if e.get('kind') not in KINDS:return False,'INVALID_TARGET_EVIDENCE_KIND'
    if calc_hash(e)!=e.get('evidence_hash'):return False,'TARGET_EVIDENCE_HASH_MISMATCH'
    if e.get('candidate')!=candidate:return False,'TARGET_EVIDENCE_CANDIDATE_MISMATCH'
    if e.get('revision')!=revision:return False,'TARGET_EVIDENCE_REVISION_MISMATCH'
    if str(e.get('workspace'))!=str(workspace):return False,'TARGET_EVIDENCE_WORKSPACE_MISMATCH'
    if e.get('task_id')!=task_id:return False,'TARGET_EVIDENCE_TASK_MISMATCH'
    return True,'PASS'
def independent_key(e):return (e.get('source'),e.get('relationship'),hashlib.sha256(str(e.get('observation')).encode()).hexdigest()[:12])
