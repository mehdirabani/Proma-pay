from evidence.evidence_store import validate_evidence
LIMITS={"performance":3,"accessibility":1,"bundle":3,"visual":5,"build":0,"lint":0,"tests":0,"security":0}
def _score(x,mode="PRODUCTION"):
    if isinstance(x,(int,float)):return float(x)
    if isinstance(x,dict) and validate_evidence(x,mode=mode):return float(x["score"])
    return None
def compare_evidence(ctx):
    baseline,current=ctx["baseline"],ctx["current"]; mode=ctx.get("mode","PRODUCTION"); regressions=[]; deltas={}; missing=[]
    for m,b in baseline.items():
        bv=_score(b,mode); cv=_score(current.get(m),mode)
        if bv is None or cv is None: missing.append(m); continue
        d=round(cv-bv,2); deltas[m]=d
        if d < -LIMITS.get(m,3):regressions.append({"metric":m,"delta":d,"baseline":bv,"current":cv})
    status="REGRESSION" if regressions else "UNKNOWN" if missing else "PASS"
    return {"regression_report":{"status":status,"regressions":regressions,"deltas":deltas,"missing":missing}}
