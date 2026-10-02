from pathlib import Path
import json, os, shutil, uuid, hashlib

class DurableTransactionError(RuntimeError):pass

def _fsync_file(p):
    with open(p,"rb") as f: os.fsync(f.fileno())

def _fsync_dir(p):
    fd=os.open(str(p),os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)

class DurableFileTransaction:
    def __init__(self,workspace,txid=None):
        self.root=Path(workspace).resolve();self.txid=txid or str(uuid.uuid4())
        self.txroot=self.root/".ffx-transactions"/self.txid
        self.stage=self.txroot/"stage";self.backup=self.txroot/"backup";self.journal=self.txroot/"journal.json"
        self.ops=[]
    def _target(self,rel):
        p=(self.root/rel).resolve();p.relative_to(self.root);return p
    def begin(self,ops):
        self.stage.mkdir(parents=True,exist_ok=True);self.backup.mkdir(parents=True,exist_ok=True)
        self.ops=ops
        state={"txid":self.txid,"status":"PREPARED","ops":[]}
        for i,op in enumerate(ops):
            target=self._target(op["path"]);staged=self.stage/str(i);staged.write_bytes(op["data"] if isinstance(op["data"],bytes) else str(op["data"]).encode());_fsync_file(staged)
            backup=None
            if target.exists():
                backup=self.backup/str(i);shutil.copy2(target,backup);_fsync_file(backup)
            state["ops"].append({"path":op["path"],"staged":str(staged.relative_to(self.root)),"backup":str(backup.relative_to(self.root)) if backup else None,"existed":target.exists()})
        self.journal.write_text(json.dumps(state,indent=2));_fsync_file(self.journal);_fsync_dir(self.txroot)
        return state
    def commit(self,crash_after=None):
        state=json.loads(self.journal.read_text());state["status"]="APPLYING";self.journal.write_text(json.dumps(state));_fsync_file(self.journal)
        for i,op in enumerate(state["ops"]):
            target=self._target(op["path"]);target.parent.mkdir(parents=True,exist_ok=True)
            tmp=target.with_name(target.name+".ffx-stage");shutil.copy2(self.root/op["staged"],tmp);_fsync_file(tmp);os.replace(tmp,target);_fsync_dir(target.parent)
            if crash_after is not None and i+1==crash_after: raise DurableTransactionError("SIMULATED_CRASH")
        state["status"]="COMMITTED";self.journal.write_text(json.dumps(state));_fsync_file(self.journal);(self.txroot/"COMMIT").write_text("ok");_fsync_file(self.txroot/"COMMIT")
        return True
    @classmethod
    def recover_all(cls,workspace):
        root=Path(workspace).resolve();base=root/".ffx-transactions";results=[]
        if not base.exists():return results
        for txroot in base.iterdir():
            journal=txroot/"journal.json"
            if not journal.exists():continue
            state=json.loads(journal.read_text())
            if state.get("status")=="COMMITTED" and (txroot/"COMMIT").exists():results.append({"txid":state["txid"],"action":"KEEP_COMMITTED"});continue
            # Incomplete: roll back all targets.
            for op in state["ops"]:
                target=(root/op["path"]).resolve();target.relative_to(root)
                if op["backup"]:
                    target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(root/op["backup"],target)
                elif target.exists():target.unlink()
            state["status"]="ROLLED_BACK";journal.write_text(json.dumps(state))
            results.append({"txid":state["txid"],"action":"ROLLED_BACK"})
        return results
