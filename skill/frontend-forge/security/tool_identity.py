import shutil, subprocess, hashlib
from pathlib import Path

def identify_tool(tool):
    path=shutil.which(tool)
    if not path:
        return {"status":"TOOL_UNAVAILABLE","resolved_path":None,"binary_hash":None,"version":None}
    p=Path(path).resolve()
    try: h=hashlib.sha256(p.read_bytes()).hexdigest()
    except Exception: h=None
    version=None
    for args in ([path,"--version"],[path,"-v"]):
        try:
            r=subprocess.run(args,capture_output=True,text=True,timeout=4,shell=False)
            if r.returncode==0:
                version=(r.stdout or r.stderr).strip().splitlines()[0][:240];break
        except Exception: pass
    return {"status":"AVAILABLE","resolved_path":str(p),"binary_hash":h,"version":version,"package_source":"PATH"}
