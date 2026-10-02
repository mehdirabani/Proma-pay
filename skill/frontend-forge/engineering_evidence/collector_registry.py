from pathlib import Path
import hashlib, json

ROOT=Path(__file__).resolve().parents[1]
REGISTRY_PATH=ROOT/"engineering_evidence/collector-registry.json"

def sha(path):
    p=Path(path)
    return hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else None

def load_registry():
    return json.loads(REGISTRY_PATH.read_text())

def validate_collector(collector_id, allowed_claim):
    reg=load_registry().get(collector_id)
    if not reg:return False,"UNTRUSTED_COLLECTOR"
    if allowed_claim not in reg.get("allowed_claim_types",[]):return False,"UNAUTHORIZED_CLAIM_TYPE"
    path=ROOT/reg["implementation"]
    if sha(path)!=reg.get("code_hash"):return False,"COLLECTOR_CODE_HASH_MISMATCH"
    return True,"PASS"
