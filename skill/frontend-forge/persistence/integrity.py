import hashlib, hmac, json, os, secrets, stat
from pathlib import Path

def _home():
    p=Path(os.environ.get("FFX_RUNTIME_STATE_HOME",Path.home()/".frontend-forge-x")).expanduser().resolve()
    p.mkdir(parents=True,exist_ok=True);return p

def _key():
    p=_home()/"persistence-integrity.key"
    if not p.exists():
        p.write_bytes(secrets.token_bytes(32))
        try:os.chmod(p,stat.S_IRUSR|stat.S_IWUSR)
        except OSError:pass
    return p.read_bytes()

def canonical(x):return json.dumps(x,sort_keys=True,separators=(",",":"),ensure_ascii=False).encode()
def mac(x):return hmac.new(_key(),canonical(x),hashlib.sha256).hexdigest()
def verify(x,signature):return hmac.compare_digest(mac(x),str(signature))
def hash_obj(x):return hashlib.sha256(canonical(x)).hexdigest()
