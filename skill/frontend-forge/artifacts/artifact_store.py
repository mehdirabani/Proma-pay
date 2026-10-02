from pathlib import Path
from datetime import datetime,timezone
import hashlib,uuid
from security.path_policy import resolve_in_workspace

def _hash(path):return hashlib.sha256(Path(path).read_bytes()).hexdigest()
def register_artifact(path,artifact_type,created_by,session_id=None,store=None,workspace=None):
    if workspace is not None:p=resolve_in_workspace(workspace,path,must_exist=True)
    else:p=Path(path).resolve()
    rec={"artifact_id":str(uuid.uuid4()),"type":artifact_type,"path":str(p),"created_by":created_by,
         "hash":_hash(p),"timestamp":datetime.now(timezone.utc).isoformat(),"session_id":session_id}
    if store:store.add_artifact(rec)
    return rec
