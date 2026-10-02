def _relations(node):
    out=[]
    for kind,key in [('DIRECT','consumers'),('DIRECT','reverse_imports'),('TYPE','types'),('ROUTE','routes'),('TEST','tests'),('STYLE','styles'),('PACKAGE','packages'),('PUBLIC_API','public_consumers')]:
        for x in node.get(key,[]) or []:out.append({'path':x,'type':kind})
    return out

def predict(change_files,graph):
    impacts=[];seen=set(change_files);frontier=list(change_files)
    for depth in range(2):
        nxt=[]
        for f in frontier:
            for e in _relations(graph.get(f,{})):
                if e['path'] in seen:continue
                seen.add(e['path']);impacts.append({**e,'source':f,'depth':depth+1,'type':e['type'] if depth==0 else 'TRANSITIVE'});nxt.append(e['path'])
        frontier=nxt
    tests=sorted({x['path'] for x in impacts if x['type']=='TEST'})
    public=any(x['type']=='PUBLIC_API' for x in impacts);centrality=graph_centrality(change_files,graph)
    size=len(impacts);risk='LOW' if size<=2 and centrality<.3 and not public else 'MEDIUM' if size<=8 and centrality<.65 else 'HIGH'
    return {'direct':sorted({x['path'] for x in impacts if x['depth']==1}),'indirect':sorted({x['path'] for x in impacts if x['depth']>1}),
        'typed_impacts':impacts,'risk':risk,'tests_required':tests,'centrality':round(centrality,4),'public_api_impact':public}
def graph_centrality(change_files,graph):
    total=max(1,len(graph));vals=[]
    for f in change_files:
        n=graph.get(f,{})
        degree=len(set(sum([list(n.get(k,[]) or []) for k in ('consumers','reverse_imports','imports','routes','tests','styles','public_consumers')],[])))
        vals.append(min(1,degree/max(1,total-1)))
    return max(vals) if vals else 0.0
def evaluate_prediction(predicted,actual):
    p=set(predicted);a=set(actual);tp=len(p&a)
    return {'precision':tp/len(p) if p else (1.0 if not a else 0.0),'recall':tp/len(a) if a else 1.0}
