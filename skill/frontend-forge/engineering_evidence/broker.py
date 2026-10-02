from pathlib import Path
import hashlib, os, time, uuid
from provenance.signer_client import SignerClient
from provenance.verifier import workspace_fingerprint
from .collector_registry import validate_collector, load_registry
from .relevance import task_relevance

def _artifact_hash(workspace,source_artifact):
    p=(Path(workspace)/source_artifact).resolve()
    try:p.relative_to(Path(workspace).resolve())
    except ValueError:raise ValueError("ARTIFACT_OUTSIDE_WORKSPACE")
    return hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() and p.is_file() else hashlib.sha256(str(source_artifact).encode()).hexdigest()

class EngineeringEvidenceBroker:
    def __init__(self, workspace, revision, task_id, session_id="session", state_dir=None):
        self.workspace=str(Path(workspace).resolve());self.revision=str(revision);self.task_id=str(task_id);self.session_id=str(session_id)
        self.state_dir=state_dir or os.environ.get("FFX_RUNTIME_STATE_HOME",str(Path.home()/".frontend-forge-x"))
        self.broker_id="engineering-"+uuid.uuid4().hex[:12];self.secret=os.urandom(32)
        self.signer=SignerClient(self.state_dir,self.broker_id,self.secret)
    def close(self):self.signer.close()
    def issue(self, collector_id, raw, task, candidate):
        claim_type=raw["claim_type"];ok,reason=validate_collector(collector_id,claim_type)
        if not ok:raise PermissionError(reason)
        reg=load_registry()[collector_id]
        rel=task_relevance(task,{**raw,"claim":claim_type})
        record={
          "record_type":"engineering_evidence","evidence_id":str(uuid.uuid4()),
          "evidence_type":claim_type,"claim_type":claim_type,"claim":claim_type,
          "candidate":str(candidate),"task_id":self.task_id,"session_id":self.session_id,
          "workspace_fingerprint":workspace_fingerprint(self.workspace,self.revision),"project_revision":self.revision,
          "collector_id":collector_id,"collector_version":reg["version"],"collector_code_hash":reg["code_hash"],
          "source_artifact":raw["source_artifact"],"source_artifact_hash":_artifact_hash(self.workspace,raw["source_artifact"]),
          "observation":raw["observation"],"producer_id":collector_id,"source_id":raw.get("source_id",collector_id),
          "derivation_parent_ids":raw.get("derivation_parent_ids",[]),"task_relevance":rel,
          "directness":float(raw.get("directness",0.5)),"metadata":raw.get("metadata",{}),
          "created_at":time.time(),"execution_id":"eng-evidence-"+uuid.uuid4().hex,
          "capability_id":"engineering-evidence","requested_capability_id":"engineering-evidence",
        }
        signed=self.signer.sign(record)
        if not signed.get("ok"):raise PermissionError(signed.get("error"))
        return signed["record"]

def create_unattested_evidence(**kwargs):
    return {"record_type":"UNATTESTED_EVIDENCE",**kwargs}
