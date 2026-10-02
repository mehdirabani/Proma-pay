def create_ledger(criteria):
    return [{'criterion':c,'status':'UNVERIFIED','evidence':[]} for c in (criteria or [])]
def apply_results(ledger,results):
    by={r.get('criterion'):r for r in (results or [])}
    out=[]
    for x in ledger:
        r=by.get(x['criterion'])
        if not r:out.append(dict(x));continue
        out.append({'criterion':x['criterion'],'status':r.get('status','UNVERIFIED'),'evidence':list(r.get('evidence') or [])})
    return out
def coverage(ledger):
    total=len(ledger);passed=sum(1 for x in ledger if x.get('status')=='PASS' and x.get('evidence'))
    return passed/total if total else 1.0
