from pathlib import Path
import sys,json,tempfile,time,statistics,hashlib,random
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT))
from .fixture import generate
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
from retrieval.two_stage import TwoStageRetriever
from token_intelligence.token_meter import DeterministicTokenEstimator
from benchmarks.claim_policy import claim_text
from repository_intelligence.context_candidate_builder import ContextCandidateBuilder
from token_intelligence.token_meter import DeterministicTokenEstimator
TASKS=json.loads((Path(__file__).parent/'public_tasks.json').read_text())
HIDDEN=json.loads((Path(__file__).parent/'hidden_ground_truth.json').read_text())
def prompt_tokens(rows):
 est=DeterministicTokenEstimator();return sum(est.estimate(x.get('text','')).tokens for x in rows)
def naive_baseline(repo,index,task,limit=12):
 rows=ContextCandidateBuilder(repo,index).build(task);words=[w.lower() for w in task.replace('/',' ').split() if len(w)>2];sc=[]
 for c in rows:
  hay=(c['path']+' '+c.get('text','')[:500]).lower();sc.append((sum(w in hay for w in words),c))
 return [x[1] for x in sorted(sc,key=lambda x:(-x[0],x[1]['path']))[:limit]]
def eval_retrieval(paths,h):
 s=set(paths);r=set(h['required']);tp=len(s&r);return {'precision':tp/max(1,len(s)),'recall':tp/max(1,len(r)),'critical_recall':len(s&r)/max(1,len(r))}
def scale_repo(root,n):
 root=Path(root);(root/'src').mkdir(parents=True,exist_ok=True)
 for i in range(n):
  dep=f"import {{F{i-1}}} from './F{i-1}'\n" if i>0 and i%4==0 else ''
  (root/'src'/f'F{i}.ts').write_text(dep+f'export const F{i}={i}\n')
 return root
def run(outdir):
 out=Path(outdir);out.mkdir(parents=True,exist_ok=True);results=[]
 with tempfile.TemporaryDirectory() as d:
  repo=generate(Path(d)/'multi',noise=100);idx=RepositoryIndexer(repo).scan();engine=TargetDiscoveryEngine(idx);retr=TwoStageRetriever(repo,idx)
  for t in TASKS:
   disc=engine.discover(t['task']);rr=retr.retrieve(t['task'],budget_candidates=6,max_depth=1);paths=[x['path'] for x in rr['loaded']];h=HIDDEN[t['id']];q=eval_retrieval(paths,h)
   baseline=naive_baseline(repo,idx,t['task']);bq=eval_retrieval([x['path'] for x in baseline],h);bt=prompt_tokens(baseline);ot=prompt_tokens(rr['loaded']);saving=(1-ot/bt)*100 if bt else 0
   results.append({'id':t['id'],'lang':t['lang'],'task_type':t['type'],'target_ok':bool(disc['targets']) and disc['targets'][0]['path']==h['target'],'predicted_confidence':disc['confidence'],**q,'selected':paths,'io':rr['io_metrics'],'baseline_prompt_estimated_tokens':bt,'optimized_prompt_estimated_tokens':ot,'estimated_context_reduction_pct':round(saving,2),'baseline_context':bq,'optimization_accepted':q['critical_recall']>=bq['critical_recall'] and q['critical_recall']==1.0 and ot<bt})
  # confidence calibration: mean abs error against binary target correctness
  cal=statistics.mean(abs(x['predicted_confidence']-(1 if x['target_ok'] else 0)) for x in results)
  # scale 100..5000
  scales=[]
  for n in [100,500,1000,5000]:
   r=scale_repo(Path(d)/f'scale{n}',n);t0=time.perf_counter();ix=RepositoryIndexer(r).scan();index_ms=(time.perf_counter()-t0)*1000
   q0=time.perf_counter();disc=TargetDiscoveryEngine(ix).discover(f'change F{n-1}');query_ms=(time.perf_counter()-q0)*1000
   scales.append({'files':n,'index_ms':round(index_ms,2),'query_ms':round(query_ms,3),'bytes_read':ix['io_metrics']['bytes_read'],'files_opened':ix['io_metrics']['files_opened'],'target_found':bool(disc['targets'])})
  target_acc=sum(x['target_ok'] for x in results)/len(results);prec=statistics.mean(x['precision'] for x in results);rec=statistics.mean(x['recall'] for x in results);crit=min(x['critical_recall'] for x in results)
  langs={lang:{'target_accuracy':sum(x['target_ok'] for x in results if x['lang']==lang)/sum(1 for x in results if x['lang']==lang)} for lang in {'en','fa'}}
  savings=[x['estimated_context_reduction_pct'] for x in results if x['optimization_accepted']]
  (out/'TOKEN_EFFICIENCY_REPORT.json').write_text(json.dumps({'claim_level':'ESTIMATED_FIXTURE','measurement_method':'deterministic-char-lexical-v1','mean_estimated_context_reduction_pct':statistics.mean(savings) if savings else None,'median_estimated_context_reduction_pct':statistics.median(savings) if savings else None,'runs':results},indent=2))
  (out/'TARGET_RESOLUTION_REPORT.json').write_text(json.dumps({'target_accuracy':target_acc,'runs':results},indent=2))
  (out/'RETRIEVAL_CONFIDENCE_REPORT.json').write_text(json.dumps({'mean_absolute_calibration_error':cal,'runs':[{'id':x['id'],'confidence':x['predicted_confidence'],'correct':x['target_ok']} for x in results]},indent=2))
  (out/'CONTEXT_PRECISION_RECALL.json').write_text(json.dumps({'mean_precision':prec,'mean_recall':rec,'minimum_critical_recall':crit,'runs':results},indent=2))
  (out/'MULTILINGUAL_TARGET_REPORT.json').write_text(json.dumps(langs,indent=2,ensure_ascii=False))
  (out/'TARGET_DIVERSITY_REPORT.json').write_text(json.dumps({'unique_targets':len(set(HIDDEN[x['id']]['target'] for x in TASKS)),'unique_task_types':len(set(x['type'] for x in TASKS)),'languages':sorted(set(x['lang'] for x in TASKS))},indent=2))
  (out/'REPOSITORY_SCALE_REPORT.json').write_text(json.dumps({'measurement':'LOCAL_RUNTIME','runs':scales},indent=2))
  (out/'INDEX_PERFORMANCE_REPORT.json').write_text(json.dumps({'runs':scales,'5000_file_completed':any(x['files']==5000 and x['target_found'] for x in scales)},indent=2))
  (out/'IO_COST_REPORT.json').write_text(json.dumps({'units':{'bytes_read':'bytes','index_ms':'milliseconds','query_ms':'milliseconds'},'runs':scales},indent=2))
  (out/'REAL_AGENT_BENCHMARK.json').write_text(json.dumps({'status':'UNVERIFIED','reason':'no real provider/agent host connected','usage_source':'none'},indent=2))
  (out/'REAL_TOKEN_USAGE_REPORT.json').write_text(json.dumps({'ACTUAL_LLM_TOKEN_REDUCTION':'UNVERIFIED','provider_usage':'UNAVAILABLE','claim_level':'ESTIMATED_FIXTURE'},indent=2))
  (out/'ENGINEERING_TASK_SUCCESS_REPORT.json').write_text(json.dumps({'status':'PARTIALLY_VERIFIED','context_success_is_not_task_success':True,'real_agent_task_success':'UNVERIFIED','deterministic_evaluator':'IMPLEMENTED'},indent=2))
  (out/'COST_DOMAIN_REPORT.json').write_text(json.dumps({'domains':{'llm_cost':{'unit':'provider_tokens','status':'UNVERIFIED'},'prompt_payload':{'unit':'estimated_tokens','status':'ESTIMATED'},'local_runtime':{'units':['milliseconds','bytes','MB'],'status':'MEASURED_LOCAL'}},'mixed_total_forbidden':True},indent=2))
  (out/'BREAK_EVEN_REPORT_V3.json').write_text(json.dumps({'provider_token_break_even':'UNVERIFIED','prompt_payload_break_even':'ESTIMATED_ONLY','runtime_time_break_even':'NOT_ESTABLISHED','storage_cost_unit':'bytes'},indent=2))
  (out/'TOKEN_ABLATION_REPORT.json').write_text(json.dumps({'status':'UNVERIFIED','reason':'isolated contribution not claimed without real agent repeated runs'},indent=2))
  (out/'ALIAS_REEXPORT_REPORT.json').write_text(json.dumps({'tsconfig_alias':'VERIFIED_BY_TEST','barrel_reexport':'VERIFIED_BY_TEST','parser_mode':idx['parser_mode']},indent=2))
  return {'target_accuracy':target_acc,'mean_precision':prec,'mean_recall':rec,'critical_recall':crit,'confidence_calibration_error':cal,'scale':scales}
if __name__=='__main__':print(json.dumps(run(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2))
