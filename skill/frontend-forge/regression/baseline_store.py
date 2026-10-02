from pathlib import Path
import json,time,uuid,hashlib
from provenance.verifier import verify_signed,workspace_fingerprint

class BaselineStore:
    def __init__(self,root,authority=None):
        self.root=Path(root);self.root.mkdir(parents=True,exist_ok=True);self.authority=authority

    def put(self,name,data):
        """Legacy/local baseline API. Not valid as a signed Production baseline."""
        versions=sorted(self.root.glob(f"{name}-v*.json"));ver=len(versions)+1
        p=self.root/f"{name}-v{ver}.json"
        p.write_text(json.dumps({"version":ver,"created_at":time.time(),"data":data,"provenance":"legacy-local"},indent=2))
        return p
    def latest(self,name):
        versions=sorted(self.root.glob(f"{name}-v*.json"),key=lambda p:int(p.stem.rsplit("v",1)[1]))
        return json.loads(versions[-1].read_text()) if versions else None
    def put_production(self,name,data,*,project_revision,workspace,evidence_refs):
        if not self.authority:raise PermissionError("trusted authority required")
        from evidence.evidence_store import validate_evidence
        if not evidence_refs or any(not validate_evidence(x,"PRODUCTION") for x in evidence_refs):
            raise ValueError("validated production evidence required")
        versions=sorted(self.root.glob(f"{name}-v*.json"));ver=len(versions)+1
        payload={"baseline_id":str(uuid.uuid4()),"name":name,"version":ver,"project_revision":project_revision,
                 "workspace_fingerprint":workspace_fingerprint(workspace,project_revision),"created_at":time.time(),
                 "evidence_ids":[e["evidence_id"] for e in evidence_refs],"data":data}
        payload["content_hash"]=hashlib.sha256(json.dumps(data,sort_keys=True).encode()).hexdigest()
        signed=self.authority.sign_baseline(payload)
        p=self.root/f"{name}-v{ver}.json";p.write_text(json.dumps(signed,indent=2));return p
    def latest_verified(self,name,*,project_revision,workspace):
        from provenance.verifier import verify_signed
        versions=sorted(self.root.glob(f"{name}-v*.json"),key=lambda p:int(p.stem.rsplit("v",1)[1]))
        if not versions:return None
        d=json.loads(versions[-1].read_text())
        if not verify_signed(d):raise ValueError("BASELINE_INTEGRITY_FAILURE")
        if d["project_revision"]!=project_revision or d["workspace_fingerprint"]!=workspace_fingerprint(workspace,project_revision):
            raise ValueError("BASELINE_REPLAY_BLOCKED")
        if d["content_hash"]!=hashlib.sha256(json.dumps(d["data"],sort_keys=True).encode()).hexdigest():
            raise ValueError("BASELINE_INTEGRITY_FAILURE")
        return d
