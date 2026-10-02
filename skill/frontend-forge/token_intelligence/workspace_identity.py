from pathlib import Path
import hashlib,subprocess
def identity(root):
    root=Path(root).resolve();remote='';rev='UNKNOWN'
    try:remote=subprocess.run(['git','config','--get','remote.origin.url'],cwd=root,capture_output=True,text=True,timeout=2).stdout.strip()
    except Exception:pass
    try:rev=subprocess.run(['git','rev-parse','HEAD'],cwd=root,capture_output=True,text=True,timeout=2).stdout.strip() or 'UNKNOWN'
    except Exception:pass
    basis=remote or (root/'package.json').read_text(errors='ignore') if (root/'package.json').exists() else root.name
    return {'repository_id':hashlib.sha256(str(basis).encode()).hexdigest()[:20],'revision':rev,'relative_root':'.'}
