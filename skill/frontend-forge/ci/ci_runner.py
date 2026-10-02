from runtime_py.capability_executor import execute_capability
from runtime_py.io_utils import load_json

def ci_outcome(status,required=True):
    model=load_json("runtime/status-model.json")
    if status not in model:return "FAIL"  # unknown fail closed
    if not required and status in {"TOOL_UNAVAILABLE","UNVERIFIED","PARTIAL","UNIMPLEMENTED"}:return "PARTIAL"
    return model[status]["ci_required"]

def run_ci(workspace,steps=("typescript-check","eslint-check","build-check"),required=None):
    required=set(required or steps);out={};final="PASS"
    for step in steps:
        ctx={"workspace":workspace}
        if step=="build-check":ctx["script"]="build"
        res=execute_capability(step,ctx,mode="CI")
        out[step]=res
        status=res.get("status","UNKNOWN_STATUS")
        verdict=ci_outcome(status,required=step in required)
        if verdict=="FAIL":final="FAIL"
        elif verdict=="BLOCKED" and final!="FAIL":final="BLOCKED"
        elif verdict=="CANCELLED" and final not in {"FAIL","BLOCKED"}:final="CANCELLED"
        elif verdict=="PARTIAL" and final=="PASS":final="PARTIAL"
    return {"status":final,"steps":out}
