from pathlib import Path
import hashlib,time
from .independence_v3 import independence

def evidence_quality(items,workspace,revision):
 if not items:return {'score':0.0,'dimensions':{'authenticity':0,'task_relevance':0,'independence':0,'directness':0,'freshness':0}}
 authentic=sum(1 for x in items if x.get('signature'))/len(items);rel=sum(float((x.get('task_relevance') or {}).get('score',0)) for x in items)/len(items);direct=sum(float(x.get('directness',0)) for x in items)/len(items)
 pairs=[independence(a,b) for i,a in enumerate(items) for b in items[i+1:]];indep=1.0 if not pairs else sum(1 for x in pairs if x=='INDEPENDENT')/len(pairs)
 fresh=0
 for x in items:
  p=(Path(workspace)/x.get('source_artifact','')).resolve();fresh+=1 if p.exists() and hashlib.sha256(p.read_bytes()).hexdigest()==x.get('source_artifact_hash') and x.get('project_revision')==str(revision) else 0
 fresh/=len(items);dims={'authenticity':authentic,'task_relevance':rel,'independence':indep,'directness':direct,'freshness':fresh};return {'score':round(sum(dims.values())/5,4),'dimensions':{k:round(v,4) for k,v in dims.items()}}
