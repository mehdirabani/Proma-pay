from engineering_evidence.relevance import domains
MECHANISMS={
 "responsive":"element sizing/wrapping constraint can exceed or resist viewport/container width",
 "accessibility":"missing/incorrect semantic association prevents keyboard or assistive interaction",
 "performance":"eager or excessive work increases load/render cost",
 "typescript":"type contract mismatch breaks assignability or compilation",
 "routing":"route/link mismatch sends navigation to incorrect location",
 "form":"control semantics prevent intended submit/field behavior",
 "async-data":"state branch suppresses required loading/error feedback",
 "css":"style declaration directly changes requested visual property",
 "nextjs":"client/server boundary changes execution/rendering behavior",
 "refactor":"duplicate structure increases divergence/maintenance risk",
}
def classify_role(task,hypothesis,evidence):
    td=domains(task);ed=domains(str(evidence.get('observation',''))+" "+str(evidence.get('metadata',{})))
    if td and ed and not (td&ed):return "INCIDENTAL"
    if (evidence.get("task_relevance") or {}).get("score",0)<.25 and td:return "INSUFFICIENT"
    obs=str(evidence.get("observation","")).lower()
    if "not " in obs or "reject" in obs:return "CONTRADICTING"
    return "SUPPORTING"
def mechanism_for(task,hypothesis,evidence):
    ds=domains(task) or domains(str(evidence.get('observation','')))
    d=next(iter(ds),None);return MECHANISMS.get(d,"observed code behavior plausibly produces the reported symptom")
