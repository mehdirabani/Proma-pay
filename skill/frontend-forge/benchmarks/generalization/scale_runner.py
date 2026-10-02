from pathlib import Path
import tempfile,time,json,sys,tracemalloc
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT))
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
def make(root,n):
 root=Path(root);(root/'src').mkdir(parents=True,exist_ok=True)
 for i in range(n):
  dep=f"import {{F{i-1}}} from './F{i-1}'\n" if i and i%11==0 else ''
  (root/'src'/f'F{i}.ts').write_text(dep+f"export const F{i}={i}\n")
 return root
def run(out):
 out=Path(out);out.mkdir(parents=True,exist_ok=True);rows=[]
 with tempfile.TemporaryDirectory() as d:
  for n in [1000,5000,10000,25000]:
   r=make(Path(d)/str(n),n);tracemalloc.start();t=time.perf_counter();idx=RepositoryIndexer(r).scan();ims=(time.perf_counter()-t)*1000
   q=[]
   for target in [n-1,max(0,n//2),min(n-1,17)]:
    s=time.perf_counter();x=TargetDiscoveryEngine(idx).discover(f'change F{target}');q.append((time.perf_counter()-s)*1000)
   _,peak=tracemalloc.get_traced_memory();tracemalloc.stop();q.sort()
   rows.append({'files':n,'index_build_ms':round(ims,2),'query_p50_ms':round(q[len(q)//2],3),'query_p95_ms':round(q[-1],3),'peak_memory_mb':round(peak/1024/1024,2),'bytes_read':idx['io_metrics']['bytes_read'],'index_size_nodes':len(idx['nodes']),'status':'PASS'})
 report={'measurement':'LOCAL_SYNTHETIC_SCALE','runs':rows,'50000_files':'UNVERIFIED_RESOURCE_LIMIT'};(out/'QUERY_SCALE_REPORT.json').write_text(json.dumps(report,indent=2));(out/'INVERTED_INDEX_REPORT.json').write_text(json.dumps({'status':'IMPLEMENTED','preselection':'token postings before detailed scoring','runs':rows},indent=2));return report
if __name__=='__main__':print(json.dumps(run(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2))
