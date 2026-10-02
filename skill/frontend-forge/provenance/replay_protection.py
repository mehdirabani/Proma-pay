import time
from .verifier import verify_signed, workspace_fingerprint

def validate_receipt_binding(receipt, *, session_id, task_id, project_revision, workspace,
                             capability_id=None, max_age_seconds=3600):
    if not verify_signed(receipt): return False, "INVALID_SIGNATURE"
    checks = [
        (receipt.get("session_id")==session_id, "SESSION_MISMATCH"),
        (receipt.get("task_id")==task_id, "TASK_MISMATCH"),
        (receipt.get("project_revision")==project_revision, "REVISION_MISMATCH"),
        (receipt.get("workspace_fingerprint")==workspace_fingerprint(workspace,project_revision), "WORKSPACE_MISMATCH"),
    ]
    if capability_id is not None:
        checks.append((receipt.get("capability_id")==capability_id, "CAPABILITY_MISMATCH"))
    for ok, err in checks:
        if not ok: return False, err
    completed = float(receipt.get("completed_at",0))
    if max_age_seconds is not None and time.time()-completed > max_age_seconds:
        return False, "STALE_RECEIPT"
    return True, "PASS"

class ReplayCache:
    def __init__(self): self._used=set()
    def consume(self, receipt):
        rid=receipt.get("receipt_id")
        if not rid or rid in self._used: return False
        self._used.add(rid); return True
