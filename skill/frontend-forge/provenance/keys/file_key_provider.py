from pathlib import Path
import json,time,os
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives import serialization
from .base_key_provider import SigningKeyProvider
class FileKeyProvider(SigningKeyProvider):
 def __init__(self,state_dir):
  self.root=Path(state_dir).resolve()/"signer-keys";self.root.mkdir(parents=True,exist_ok=True)
  try: os.chmod(self.root,0o700)
  except OSError: pass
  self.meta=self.root/'keys.json';self._ensure()
 def _ensure(self):
  if self.meta.exists():return
  kid=f'k-{int(time.time()*1000)}';k=Ed25519PrivateKey.generate()
  (self.root/f'{kid}.pem').write_bytes(k.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
  (self.root/f'{kid}.pub').write_bytes(k.public_key().public_bytes(serialization.Encoding.Raw,serialization.PublicFormat.Raw))
  try: os.chmod(self.root/f'{kid}.pem',0o600)
  except OSError:pass
  self.meta.write_text(json.dumps({'active':kid,'keys':{kid:{'status':'ACTIVE','created_at':time.time(),'expires_at':None}}},indent=2))
 def _m(self):return json.loads(self.meta.read_text())
 def key_id(self):return self._m()['active']
 def _priv(self):
  from cryptography.hazmat.primitives.serialization import load_pem_private_key
  return load_pem_private_key((self.root/f'{self.key_id()}.pem').read_bytes(),password=None)
 def sign(self,payload):return self._priv().sign(payload)
 def public_key(self):return (self.root/f'{self.key_id()}.pub').read_bytes()
 def metadata(self):return self._m()['keys'][self.key_id()]
 def rotate(self):
  m=self._m();m['keys'][m['active']]['status']='VERIFY_ONLY';self.meta.write_text(json.dumps(m));self.meta.unlink();self._ensure();return self.key_id()
 def revoke(self,kid):
  m=self._m();m['keys'][kid]['status']='REVOKED';self.meta.write_text(json.dumps(m,indent=2))
