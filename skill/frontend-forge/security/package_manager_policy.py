import json
from pathlib import Path

class PackagePolicyError(ValueError): pass

def load_package_json(workspace):
    p=Path(workspace).resolve()/"package.json"
    if not p.exists(): raise PackagePolicyError("package.json missing")
    return json.loads(p.read_text(encoding="utf-8"))

def lifecycle_names(script):
    return [f"pre{script}",script,f"post{script}"]

def execution_plan(workspace, requested, manager="npm"):
    if manager not in {"npm","pnpm","yarn"}: raise PackagePolicyError("UNSUPPORTED_PACKAGE_MANAGER")
    pkg=load_package_json(workspace); scripts=pkg.get("scripts",{})
    names=lifecycle_names(requested)
    commands=[{"name":n,"command":scripts[n]} for n in names if n in scripts]
    risk="HIGH" if any(_risky(x["command"]) for x in commands) else "LOW"
    return {"requested":requested,"manager":manager,"lifecycle":names,"commands":commands,"risk":risk}

def _risky(command):
    c=command.lower()
    risky=("curl ","wget ","powershell","nc ","netcat","ssh ","rm -rf","../","process.env","child_process","chmod 777")
    return any(x in c for x in risky)

def validate_plan(plan, *, untrusted=True):
    if untrusted and plan["risk"]=="HIGH": raise PackagePolicyError("SECURITY_BLOCKED")
    return True

def install_policy(*, trust_level, isolated, lockfile_present, ignore_scripts=True, network_mode="DENY"):
    if trust_level in {"PROJECT_UNTRUSTED","EXTERNAL_UNTRUSTED"}:
        if not isolated: return {"status":"BLOCKED","reason":"full isolation required"}
        if not lockfile_present: return {"status":"BLOCKED","reason":"lockfile required"}
        if network_mode=="FULL": return {"status":"BLOCKED","reason":"full network not allowed"}
    return {"status":"PASS","ignore_scripts":bool(ignore_scripts),"network_mode":network_mode}
