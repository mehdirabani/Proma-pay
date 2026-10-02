import hashlib,time,uuid,os
from pathlib import Path
from provenance.signer_client import SignerClient
def _hash_file(p):
    p=Path(p);return hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else None
def sign_change_receipt(workspace,*,task_id,session_id,plan_id,root_cause_id,target_evidence_ids,transaction_id,changes,revision_before='UNKNOWN',revision_after='UNKNOWN',state_dir=None):
    root=Path(workspace).resolve();broker_id="change-"+uuid.uuid4().hex[:12];secret=os.urandom(32)
    signer=SignerClient(state_dir or os.environ.get("FFX_RUNTIME_STATE_HOME",str(Path.home()/".frontend-forge-x")),broker_id,secret)
    record={"record_type":"engineering_change","change_receipt_id":str(uuid.uuid4()),"task_id":task_id,"session_id":session_id,"plan_id":plan_id,
      "root_cause_id":root_cause_id,"target_evidence_ids":list(target_evidence_ids),"transaction_id":transaction_id,
      "files_changed":[c["path"] for c in changes],
      "before_hashes":{c["path"]:hashlib.sha256(c["before"].encode()).hexdigest() for c in changes},
      "after_hashes":{c["path"]:hashlib.sha256(c["after"].encode()).hexdigest() for c in changes},
      "project_revision_before":revision_before,"project_revision_after":revision_after,"created_at":time.time(),
      "execution_id":"change-"+uuid.uuid4().hex,"capability_id":"engineering-change","requested_capability_id":"engineering-change"}
    try:
        r=signer.sign(record)
        if not r.get("ok"):raise PermissionError(r.get("error"))
        return r["record"]
    finally:signer.close()
