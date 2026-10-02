from pathlib import Path
import hashlib
from provenance.verifier import verify_signed,workspace_fingerprint
from .contracts import REQUIRED
from .collector_registry import validate_collector
def verify_engineering_evidence(e,*,workspace,revision,task_id,candidate=None,state_dir=None):
    if not isinstance(e,dict) or not REQUIRED.issubset(e):return False,"INVALID_ENGINEERING_EVIDENCE_CONTRACT"
    if e.get("record_type")!="engineering_evidence":return False,"UNATTESTED_EVIDENCE"
    if not verify_signed(e,state_dir=state_dir):return False,"INVALID_ENGINEERING_EVIDENCE_SIGNATURE"
    if e.get("project_revision")!=str(revision):return False,"EVIDENCE_REVISION_MISMATCH"
    if e.get("task_id")!=str(task_id):return False,"EVIDENCE_TASK_MISMATCH"
    if e.get("workspace_fingerprint")!=workspace_fingerprint(workspace,revision):return False,"EVIDENCE_WORKSPACE_MISMATCH"
    if candidate is not None and e.get("candidate")!=str(candidate):return False,"EVIDENCE_CANDIDATE_MISMATCH"
    ok,reason=validate_collector(e.get("collector_id"),e.get("claim_type"))
    if not ok:return False,reason
    p=(Path(workspace)/e.get("source_artifact","")).resolve()
    try:p.relative_to(Path(workspace).resolve())
    except Exception:return False,"EVIDENCE_ARTIFACT_OUTSIDE_WORKSPACE"
    actual=hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() and p.is_file() else hashlib.sha256(str(e.get("source_artifact")).encode()).hexdigest()
    if actual!=e.get("source_artifact_hash"):return False,"EVIDENCE_ARTIFACT_HASH_MISMATCH"
    return True,"PASS"
