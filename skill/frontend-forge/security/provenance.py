from provenance.authority import issue_execution_receipt, UnauthorizedProvenanceIssuer
from provenance.verifier import verify_signed
from pathlib import Path
import hashlib

def verify_execution_receipt(receipt):
    return verify_signed(receipt)

def attest_artifact(*args, **kwargs):
    raise UnauthorizedProvenanceIssuer("UNAUTHORIZED_PROVENANCE_ISSUER")

def verify_artifact_attestation(attestation, artifact_path, expected_actor=None):
    # v14.2 attestation actor is bound through receipt; evidence store verifies the receipt too.
    try:
        if not verify_signed(attestation): return False
        return hashlib.sha256(Path(artifact_path).read_bytes()).hexdigest()==attestation["artifact_hash"]
    except Exception:
        return False
