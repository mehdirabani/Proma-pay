from pathlib import Path
from datetime import datetime,timezone
import hashlib,uuid,json,time
from security.path_policy import resolve_in_workspace
from evidence.parsers import get_parser
from provenance.verifier import verify_signed,workspace_fingerprint
from provenance.replay_protection import validate_receipt_binding

def _hash(p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()

def create_evidence(metric,score,source,tool_version,artifact):
    # Simulation-only synthetic evidence.
    p=Path(artifact).resolve()
    return {"evidence_id":str(uuid.uuid4()),"metric":metric,"score":float(score),"source":source,
            "tool_version":tool_version,"timestamp":datetime.now(timezone.utc).isoformat(),
            "artifact":str(p),"hash":_hash(p),"provenance":"synthetic"}

def create_tool_evidence(metric,source,tool_version,artifact,workspace,artifact_attestation=None,
                         execution_receipt=None,session_id=None,task_id=None,project_revision=None,
                         capability_id=None,max_age_seconds=3600):
    if not execution_receipt or not artifact_attestation:
        raise ValueError("trusted receipt and artifact attestation required")
    ok,reason=validate_receipt_binding(execution_receipt,session_id=session_id,task_id=task_id,
        project_revision=project_revision,workspace=workspace,capability_id=capability_id,max_age_seconds=max_age_seconds)
    if not ok:raise ValueError(reason)
    if not verify_signed(artifact_attestation):raise ValueError("invalid attestation signature")
    if artifact_attestation.get("receipt_id")!=execution_receipt.get("receipt_id"):raise ValueError("receipt/attestation mismatch")
    p=resolve_in_workspace(workspace,artifact,must_exist=True)
    if _hash(p)!=artifact_attestation.get("artifact_hash"):raise ValueError("artifact hash mismatch")
    if artifact_attestation.get("session_id")!=session_id or artifact_attestation.get("project_revision")!=project_revision:
        raise ValueError("attestation replay detected")
    parser=get_parser(source);score,derived=parser.extract(metric,p)
    artifact_hash=_hash(p)
    derivation={"parser_id":parser.id,"parser_version":parser.version,"parser_code_hash":parser.code_hash,
                "metric":metric,"score":score,"artifact_hash":artifact_hash,"receipt_id":execution_receipt["receipt_id"],
                "execution_id":execution_receipt["execution_id"],"derived":derived}
    dh=hashlib.sha256(json.dumps(derivation,sort_keys=True,separators=(",",":")).encode()).hexdigest()
    return {"evidence_id":str(uuid.uuid4()),"metric":metric,"score":score,"source":source,"tool_version":tool_version,
            "timestamp":datetime.now(timezone.utc).isoformat(),"artifact":str(p),"hash":artifact_hash,
            "provenance":"derived","parser_id":parser.id,"parser_version":parser.version,
            "parser_code_hash":parser.code_hash,"derivation_hash":dh,"derived":derived,
            "workspace":str(Path(workspace).resolve()),"workspace_fingerprint":workspace_fingerprint(workspace,project_revision),
            "session_id":session_id,"task_id":task_id,"project_revision":project_revision,
            "capability_id":capability_id,"execution_receipt":execution_receipt,
            "artifact_attestation":artifact_attestation}

def validate_evidence(e,mode="PRODUCTION",*,session_id=None,task_id=None,project_revision=None,workspace=None,max_age_seconds=3600):
    try:
        p=Path(e["artifact"])
        if not p.exists() or _hash(p)!=e["hash"] or not 0<=float(e["score"])<=100:return False
        if mode=="SIMULATION":return True
        if e.get("provenance")!="derived":return False
        session_id=session_id or e.get("session_id");task_id=task_id or e.get("task_id")
        project_revision=project_revision or e.get("project_revision");workspace=workspace or e.get("workspace")
        rec=e.get("execution_receipt");att=e.get("artifact_attestation")
        ok,_=validate_receipt_binding(rec,session_id=session_id,task_id=task_id,project_revision=project_revision,
            workspace=workspace,capability_id=e.get("capability_id"),max_age_seconds=max_age_seconds)
        if not ok or not verify_signed(att):return False
        if att.get("receipt_id")!=rec.get("receipt_id") or att.get("artifact_hash")!=e["hash"]:return False
        parser=get_parser(e["source"])
        if parser.id!=e.get("parser_id") or parser.version!=e.get("parser_version") or parser.code_hash!=e.get("parser_code_hash"):
            return False
        score,derived=parser.extract(e["metric"],p)
        if abs(score-float(e["score"]))>1e-9:return False
        d={"parser_id":parser.id,"parser_version":parser.version,"parser_code_hash":parser.code_hash,
           "metric":e["metric"],"score":score,"artifact_hash":e["hash"],"receipt_id":rec["receipt_id"],
           "execution_id":rec["execution_id"],"derived":derived}
        dh=hashlib.sha256(json.dumps(d,sort_keys=True,separators=(",",":")).encode()).hexdigest()
        return dh==e.get("derivation_hash")
    except Exception:return False
