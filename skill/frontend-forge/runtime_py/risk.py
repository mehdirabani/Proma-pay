def assess_risk(ctx):
    files=int(ctx.get("files_affected",0)); depth=int(ctx.get("dependency_depth",0))
    coverage=float(ctx.get("test_coverage",1.0)); production=bool(ctx.get("production_impact",False))
    score=min(100,files*2+depth*8+(30 if coverage<0.5 else 0)+(25 if production else 0))
    level="LOW" if score<25 else "MEDIUM" if score<50 else "HIGH" if score<75 else "CRITICAL"
    return {"risk_report":{"score":score,"level":level,"approval_required":level in {"HIGH","CRITICAL"}}}
