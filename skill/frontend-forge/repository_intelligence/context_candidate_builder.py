from pathlib import Path
from retrieval.target_discovery import TargetDiscoveryEngine
from routing.intent_normalizer import normalize_intent
class ContextCandidateBuilder:
    def __init__(self,root,index):self.root=Path(root).resolve();self.index=index
    def build_metadata(self,task,changed_files=()):
        disc=TargetDiscoveryEngine(self.index).discover(task,changed_files);targets={x['path'] for x in disc['targets'][:3]};nodes=self.index['nodes'];out=[]
        related=set(targets)
        for t in targets:
            n=nodes[t]
            for e in ('imports','reexports','reverse_imports','tests','styles','routes'):related.update(x for x in n.get(e,[]) if x in nodes)
        for rel,n in nodes.items():
            out.append({'path':rel,'content_loaded':False,'targets':[rel] if rel in targets else [],'direct_dependency':any(rel in nodes[t].get('imports',[])+nodes[t].get('reexports',[]) for t in targets),
              'reverse_dependency':any(rel in nodes[t].get('reverse_imports',[]) for t in targets),'test_relationship':any(rel in nodes[t].get('tests',[]) for t in targets),
              'style_relationship':any(rel in nodes[t].get('styles',[]) for t in targets),'component_relationship':rel in related,'route_relationship':bool(n.get('routes')),
              'symbols':n.get('symbols',[]),'target_confidence':disc['confidence']})
        return out
    def build(self,task,changed_files=()):
        out=self.build_metadata(task,changed_files)
        for c in out:
            c['text']=(self.root/c['path']).read_text(errors='ignore');c['content_loaded']=True
        return out
