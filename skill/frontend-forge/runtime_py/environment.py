import shutil,subprocess,sys,platform,importlib.util
TOOLS=["node","npm","npx","git","pnpm","yarn","lighthouse","eslint","tsc","prettier","vitest","jest"]
def _version(tool):
    try:
        r=subprocess.run([tool,"--version"],capture_output=True,text=True,timeout=3)
        return (r.stdout or r.stderr).strip().splitlines()[0][:200]
    except Exception: return None
def scan_environment(ctx=None):
    tools={}
    for t in TOOLS:
        path=shutil.which(t)
        tools[t]={"available":bool(path),"path":path,"version":_version(t) if path else None}
    tools["playwright_python"]={"available":importlib.util.find_spec("playwright") is not None}
    pm=next((x for x in ["pnpm","yarn","npm"] if tools.get(x,{}).get("available")),None)
    return {"environment_report":{"python":sys.version.split()[0],"os":platform.platform(),"package_manager":pm,"tools":tools}}
