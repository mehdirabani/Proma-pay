import base64,json,hashlib,os
from pathlib import Path
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
def canonical(x):return json.dumps(x,sort_keys=True,separators=(",",":")).encode()
def verify_signed(record,state_dir=None):
 try:
  root=Path(state_dir or os.environ.get('FFX_RUNTIME_STATE_HOME',Path.home()/'.frontend-forge-x'));kid=record['key_id'];m=json.loads((root/'signer-keys/keys.json').read_text())['keys'][kid]
  if m['status']=='REVOKED':return False
  pub=(root/f'signer-keys/{kid}.pub').read_bytes();payload={k:v for k,v in record.items() if k!='signature'};Ed25519PublicKey.from_public_bytes(pub).verify(base64.b64decode(record['signature']),canonical(payload));return True
 except Exception:return False
def workspace_fingerprint(path,project_revision='UNKNOWN'):return hashlib.sha256((str(Path(path).resolve())+'|'+str(project_revision)).encode()).hexdigest()
