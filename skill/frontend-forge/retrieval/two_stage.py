from pathlib import Path
import time,tracemalloc
from routing.intent_normalizer import normalize_intent
from .target_discovery import TargetDiscoveryEngine
from .intent_weights import edge_weights
from .states import *
class LazyContentLoader:
    def __init__(self,root):self.root=Path(root).resolve();self.files_opened=0;self.bytes_read=0;self.filesystem_stat_calls=0
    def load(self,rel):
        p=(self.root/rel).resolve();p.relative_to(self.root);self.filesystem_stat_calls+=1;raw=p.read_bytes();self.files_opened+=1;self.bytes_read+=len(raw);return raw.decode(errors='ignore')
class TwoStageRetriever:
    def __init__(self,root,index,calibrator=None):self.root=Path(root).resolve();self.index=index;self.nodes=index['nodes'];self.calibrator=calibrator
    def _expand(self,seeds,domains,max_depth,budget_candidates):
        weights=edge_weights(domains)
        if 'accessibility' in domains:allowed={'tests','reexports'}
        elif 'behavior' in domains:allowed={'tests','imports','reexports'}
        elif 'css-tailwind' in domains:allowed={'styles','imports','reexports'}
        elif 'performance' in domains:allowed={'imports','reexports','reverse_imports','routes'}
        else:allowed={'imports','reexports','reverse_imports','tests','styles','routes'}
        queue=[(s['path'],0,s['score'],'target') for s in seeds];seen={};trace=[]
        while queue and len(seen)<budget_candidates:
            rel,depth,score,reason=queue.pop(0)
            if rel in seen or rel not in self.nodes:continue
            seen[rel]={'path':rel,'score':score,'graph_distance':depth,'reason':reason}
            if depth>=max_depth:continue
            n=self.nodes[rel]
            for edge in allowed:
                for dep in n.get(edge,[]):
                    ns=score*weights.get(edge,.4)*.82
                    if ns<.10:continue
                    if dep in self.nodes and dep not in seen:
                        queue.append((dep,depth+1,ns,edge));trace.append({'from':rel,'to':dep,'edge':edge,'score':round(ns,4)})
            queue.sort(key=lambda x:-x[2])
        return seen,trace
    def retrieve(self,task,budget_candidates=12,max_depth=2,changed_files=()):
        t0=time.perf_counter();tracemalloc.start();intent=normalize_intent(task);disc=TargetDiscoveryEngine(self.index,self.calibrator).discover(task,changed_files)
        if not disc['targets']:
            _,peak=tracemalloc.get_traced_memory();tracemalloc.stop();return {'status':'AMBIGUOUS_TARGET','state':TARGET_AMBIGUOUS,'loaded':[],'discovery':disc,'expansion_trace':[],'io_metrics':{'index_nodes_considered':0,'filesystem_stat_calls':0,'files_opened':0,'bytes_read':0,'peak_memory_mb':round(peak/1024/1024,3)}}
        domains=set(intent.get('domains') or []);low=task.lower();
        if any(x in low for x in ['open','close','state','کار نمی','باز','بستن']):domains.add('behavior')
        initial_conf=disc['confidence'];seed_count=1 if disc['state']==TARGET_RESOLVED else min(3,len(disc['targets']))
        initial_depth=0 if disc['state']==CONTEXT_EXPANSION_REQUIRED else 1
        seen,trace=self._expand(disc['targets'][:seed_count],domains,initial_depth,budget_candidates)
        state=CONTEXT_SUFFICIENT if disc['state']==TARGET_RESOLVED else CONTEXT_EXPANDED
        # Real expansion changes evidence: one extra graph ring and more candidate seeds.
        if disc['state']==CONTEXT_EXPANSION_REQUIRED:
            before=set(seen);seen2,trace2=self._expand(disc['targets'][:min(5,len(disc['targets']))],domains,max_depth,budget_candidates);seen.update(seen2);trace+=trace2
            new=sorted(set(seen)-before)
            # Evidence-based reconfidence: unrelated expansion does not increase confidence.
            target_path=disc['targets'][0]['path'];support=0;contradiction=0
            target_node=self.nodes.get(target_path,{})
            support_set=set(target_node.get('tests',[])+target_node.get('styles',[])+target_node.get('routes',[])+target_node.get('reverse_imports',[])+target_node.get('imports',[])+target_node.get('reexports',[]))
            for rel in new:
                if rel in support_set:support+=1
                elif rel in {x['path'] for x in disc['targets'][1:3]}:contradiction+=1
            delta=min(.18,.06*support)-min(.18,.08*contradiction)
            final_conf=max(0,min(.95,initial_conf+delta))
            state=CONTEXT_SUFFICIENT if support>0 and final_conf>=.45 and contradiction==0 else (TARGET_AMBIGUOUS if contradiction else INSUFFICIENT_CONTEXT)
        else:final_conf=initial_conf;new=[]
        shortlist=sorted(seen.values(),key=lambda x:(-x['score'],x['graph_distance'],x['path']))[:budget_candidates]
        loader=LazyContentLoader(self.root);loaded=[]
        for c in shortlist:
            c=dict(c);c['text']=loader.load(c['path']);c['content_loaded']=True;c['target_confidence']=final_conf;loaded.append(c)
        _,peak=tracemalloc.get_traced_memory();tracemalloc.stop()
        status='PASS' if state==CONTEXT_SUFFICIENT else state
        return {'status':status,'state':state,'loaded':loaded,'discovery':disc,'intent':intent,
          'expansion_trace':{'initial_confidence':initial_conf,'expansion_steps':trace,'new_evidence':new,'final_confidence':round(final_conf,4)},
          'io_metrics':{'index_nodes_considered':disc.get('metrics',{}).get('index_nodes_considered',0),'files_stat_ed':len(self.nodes),'filesystem_stat_calls':loader.filesystem_stat_calls,'files_opened':loader.files_opened,'bytes_read':loader.bytes_read,'index_lookups':disc.get('metrics',{}).get('index_lookups',0),'content_load_count':loader.files_opened,'retrieval_latency_ms':round((time.perf_counter()-t0)*1000,3),'peak_memory_mb':round(peak/1024/1024,3)}}
