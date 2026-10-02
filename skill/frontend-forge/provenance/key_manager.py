from pathlib import Path
import os, stat
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives import serialization

def state_home():
    p = Path(os.environ.get("FFX_RUNTIME_STATE_HOME", Path.home()/".frontend-forge-x")).expanduser().resolve()
    p.mkdir(parents=True, exist_ok=True)
    return p

def private_key_path():
    return state_home()/"provenance-ed25519.pem"

def public_key_path():
    return state_home()/"provenance-ed25519.pub"

def ensure_keypair():
    priv_path, pub_path = private_key_path(), public_key_path()
    if not priv_path.exists():
        priv = Ed25519PrivateKey.generate()
        priv_path.write_bytes(priv.private_bytes(
            encoding=serialization.Encoding.PEM,
            format=serialization.PrivateFormat.PKCS8,
            encryption_algorithm=serialization.NoEncryption()))
        try: os.chmod(priv_path, stat.S_IRUSR|stat.S_IWUSR)
        except OSError: pass
        pub_path.write_bytes(priv.public_key().public_bytes(
            encoding=serialization.Encoding.Raw,
            format=serialization.PublicFormat.Raw))
    return priv_path, pub_path

def load_private_for_authority():
    # This function is imported only by authority.py. Runtime workers use verifier.py.
    from cryptography.hazmat.primitives.serialization import load_pem_private_key
    p, _ = ensure_keypair()
    return load_pem_private_key(p.read_bytes(), password=None)

def public_key_bytes():
    _, p = ensure_keypair()
    return p.read_bytes()

def key_id():
    import hashlib
    return hashlib.sha256(public_key_bytes()).hexdigest()[:16]
