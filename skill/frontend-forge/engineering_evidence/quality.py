from .independence import independent_groups
def evidence_quality(evidence):
    if not evidence:return {"score":0.0,"dimensions":{"authenticity":0,"task_relevance":0,"independence":0,"directness":0,"freshness":0}}
    authenticity=sum(1 for e in evidence if e.get("signature"))/len(evidence)
    relevance=sum(float((e.get("task_relevance") or {}).get("score",0)) for e in evidence)/len(evidence)
    independence=min(1.0,len(independent_groups(evidence))/max(1,len(evidence)))
    directness=sum(float(e.get("directness",0)) for e in evidence)/len(evidence)
    freshness=1.0
    dims={"authenticity":authenticity,"task_relevance":relevance,"independence":independence,"directness":directness,"freshness":freshness}
    score=sum(dims.values())/len(dims)
    return {"score":round(score,4),"dimensions":{k:round(v,4) for k,v in dims.items()}}
