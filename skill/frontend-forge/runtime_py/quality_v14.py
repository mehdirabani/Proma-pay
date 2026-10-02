from .io_utils import load_json
from evidence.evidence_store import validate_evidence
STATUSES={"PASS","FAIL","UNKNOWN","PARTIAL","BLOCKED"}
def evaluate_quality(ctx):
    profile=ctx["profile"]; evidence=ctx.get("evidence",{}); mode=ctx.get("mode","PRODUCTION")
    p=load_json("quality/project-profiles.json")[profile];values={};invalid=[];missing=[]
    for metric in p["required_metrics"]:
        ev=evidence.get(metric)
        if ev is None:missing.append(metric);continue
        if mode=="SIMULATION" and isinstance(ev,(int,float)):values[metric]=float(ev);continue
        if not isinstance(ev,dict) or not validate_evidence(ev,mode=mode):invalid.append(metric);continue
        values[metric]=float(ev["score"])
    blockers=[]
    for m,t in p["blocking"].items():
        if m not in values:blockers.append({"metric":m,"reason":"missing_or_invalid_evidence","threshold":t})
        elif values[m]<t:blockers.append({"metric":m,"value":values[m],"threshold":t})
    measured_weight=sum(p["weights"][m] for m in values if m in p["weights"])
    weighted=round(sum(values[m]*p["weights"][m] for m in values if m in p["weights"])/measured_weight,2) if measured_weight else None
    if blockers:status="BLOCKED"
    elif invalid or missing:status="PARTIAL" if values else "UNKNOWN"
    elif weighted<p["minimum_overall"]:status="FAIL"
    else:status="PASS"
    return {"quality_report":{"profile":profile,"status":status,"weighted_score":weighted,"missing":missing,"invalid":invalid,"blocking_failures":blockers,"values":values}}
