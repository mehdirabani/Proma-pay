import re
DOMAIN_TERMS={
 "responsive":{"overflow","mobile","viewport","width","min-width","wrap","موبایل","بیرون","عرض","نمایشگر"},
 "accessibility":{"accessibility","accessible","keyboard","focus","label","aria","tab","دسترسی","کیبورد","تب","برچسب"},
 "performance":{"performance","slow","bundle","import","eager","lazy","سرعت","کند"},
 "typescript":{"typescript","type","prop","interface","ts2322","تایپ"},
 "routing":{"route","router","navigation","link","href","مسیر","لینک"},
 "form":{"form","checkout","payment","input","submit","فرم","پرداخت"},
 "css":{"color","background","padding","margin","css","tailwind","رنگ","فاصله"},
 "nextjs":{"next","server","client","route","app router"},
 "refactor":{"duplicate","extract","refactor","duplication","تکرار"},
}
def terms(s):
    return {x for x in re.findall(r"[a-z0-9\u0600-\u06ff-]+",str(s).lower()) if len(x)>2}
def domains(s):
    t=terms(s);return {d for d,v in DOMAIN_TERMS.items() if t&v}
def task_relevance(task,evidence):
    task_d=domains(task);obs=str(evidence.get("observation",""))
    ev_d=domains(obs)
    overlap=len(terms(task)&terms(obs))
    same=bool(task_d & ev_d)
    conflict=bool(task_d and ev_d and not same)
    link_claim=evidence.get("claim_type")=="TASK_TARGET_LINK" or evidence.get("claim")=="TASK_TARGET_LINK"
    score=min(1.0, 0.15*overlap + (0.55 if same else 0) + (0.3 if link_claim and overlap else 0))
    if conflict:score*=0.2
    return {"score":round(score,4),"task_domains":sorted(task_d),"evidence_domains":sorted(ev_d),
            "task_linking":bool(link_claim and overlap),"conflict":conflict}
