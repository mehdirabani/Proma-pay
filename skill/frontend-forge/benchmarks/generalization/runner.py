from pathlib import Path
import json,tempfile,statistics,math,sys
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT))
from .fixture import generate
from benchmarks.retrieval_v1433.fixture import generate as generate_dev
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
from retrieval.two_stage import TwoStageRetriever
from retrieval.confidence import EmpiricalConfidenceCalibrator
from repository_intelligence.context_candidate_builder import ContextCandidateBuilder
from token_intelligence.token_meter import DeterministicTokenEstimator
TASKS=json.loads((Path(__file__).parent/'public_tasks.json').read_text());GT=json.loads((Path(__file__).parent/'sealed_ground_truth.json').read_text())
DEV_TASKS=json.loads((ROOT/'benchmarks/retrieval_v1433/public_tasks.json').read_text());DEV_GT=json.loads((ROOT/'benchmarks/retrieval_v1433/hidden_ground_truth.json').read_text())
def dcg(rels):return sum(r/math.log2(i+2) for i,r in enumerate(rels))
def est_tokens(rows):
 est=DeterministicTokenEstimator();return sum(est.estimate(x.get('text','')).tokens for x in rows)
def naive(repo,index,task,limit=10):
 rows=ContextCandidateBuilder(repo,index).build(task);q={w.lower() for w in task.split() if len(w)>2};sc=[]
 for c in rows:
  hay=(c['path']+' '+c.get('text','')[:700]).lower();sc.append((sum(w in hay for w in q),c))
 return [x[1] for x in sorted(sc,key=lambda x:(-x[0],x[1]['path']))[:limit]]
def calibration_model(tmp):
 repo=generate_dev(Path(tmp)/'calibration',noise=60);idx=RepositoryIndexer(repo).scan();eng=TargetDiscoveryEngine(idx);pairs=[]
 for t in DEV_TASKS:
  r=eng.discover(t['task']);ok=bool(r['targets']) and r['targets'][0]['path']==DEV_GT[t['id']]['target'];pairs.append((r.get('raw_confidence',r['confidence']),ok))
 for q in ['quantum banana widget','unrelated finance chart','unknown footer rocket','چیز نامرتبط ناشناخته','random xyz module','weather graph panel']:
  r=eng.discover(q);pairs.append((r.get('raw_confidence',r['confidence']),False))
 return EmpiricalConfidenceCalibrator().fit(pairs,bins=5),pairs
def run(outdir):
 out=Path(outdir);out.mkdir(parents=True,exist_ok=True);rows=[]
 with tempfile.TemporaryDirectory() as d:
  cal,dev_pairs=calibration_model(d);repo=generate(Path(d)/'holdout');idx=RepositoryIndexer(repo).scan();eng=TargetDiscoveryEngine(idx,cal);ret=TwoStageRetriever(repo,idx,cal)
  cal_eval=[]
  for t in TASKS:
   x=eng.discover(t['task']);rr=ret.retrieve(t['task'],budget_candidates=6,max_depth=2);gt=GT[t['id']];rank=[z['path'] for z in x['targets']];sel=[z['path'] for z in rr['loaded']];req=set(gt['required'])
   ok=bool(rank) and rank[0]==gt['target'];cal_eval.append((x.get('raw_confidence',x['confidence']),ok));target_rank=(rank.index(gt['target'])+1) if gt['target'] in rank else None;rel=[1 if p in req else 0 for p in sel];ideal=sorted(rel,reverse=True)
   baseline=naive(repo,idx,t['task']);bt=est_tokens(baseline);ot=est_tokens(rr['loaded']);saving=round((1-ot/bt)*100,2) if bt else None
   rows.append({'id':t['id'],'lang':t['lang'],'target_ok':ok,'target_rank':target_rank,'retrieval_score':x['targets'][0]['retrieval_score'] if x['targets'] else 0,'calibrated_confidence':x['confidence'],'state':rr['state'],
    'precision':len(set(sel)&req)/max(1,len(sel)),'recall':len(set(sel)&req)/len(req),'critical_recall':len(set(sel)&req)/len(req),'mrr':1/target_rank if target_rank else 0,
    'precision_at_3':sum(rel[:3])/3,'recall_at_3':sum(rel[:3])/len(req),'ndcg_at_5':dcg(rel[:5])/(dcg(ideal[:5]) or 1),'selected':sel,'expansion_trace':rr.get('expansion_trace'),
    'baseline_estimated_tokens':bt,'forge_estimated_tokens':ot,'estimated_context_reduction_pct':saving,'optimization_accepted':saving is not None and saving>0 and len(set(sel)&req)==len(req)})
 acc=sum(r['target_ok'] for r in rows)/len(rows);critical=min(r['critical_recall'] for r in rows);prec=statistics.mean(r['precision'] for r in rows);ce=cal.evaluate(cal_eval)
 report={'measurement':'LOCAL_SEALED_HOLDOUT_FIXTURE','semantic_backend':'LEXICAL_GRAPH','target_accuracy':acc,'mean_precision':prec,'minimum_critical_recall':critical,'mean_mrr':statistics.mean(r['mrr'] for r in rows),'mean_ndcg_at_5':statistics.mean(r['ndcg_at_5'] for r in rows),'runs':rows}
 (out/'GENERALIZATION_REPORT.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));(out/'HOLDOUT_TARGET_REPORT.json').write_text(json.dumps({'runs':rows},indent=2,ensure_ascii=False));(out/'TARGET_CONFIDENCE_CALIBRATION.json').write_text(json.dumps({'method':'EMPIRICAL_BINNING_LAPLACE','calibration_set_size':len(dev_pairs),'evaluation_set':'SEALED_HOLDOUT','evaluation':ce,'bins':cal.bins,'note':'small fixture calibration; not a production probability guarantee'},indent=2));(out/'PRECISION_RECALL_AT_K.json').write_text(json.dumps({'mean_mrr':report['mean_mrr'],'mean_ndcg_at_5':report['mean_ndcg_at_5'],'mean_precision_at_3':statistics.mean(r['precision_at_3'] for r in rows),'mean_recall_at_3':statistics.mean(r['recall_at_3'] for r in rows),'critical_recall':critical},indent=2));(out/'RETRIEVAL_STATE_REPORT.json').write_text(json.dumps({'states':{s:sum(1 for r in rows if r['state']==s) for s in sorted(set(r['state'] for r in rows))},'blind_pass_from_expansion':0},indent=2))
 conf_status='CALIBRATED_FIXTURE' if ce['ece'] is not None and ce['ece']<=.20 else 'CONFIDENCE_UNCALIBRATED'
 (out/'TARGET_CONFIDENCE_CALIBRATION.json').write_text(json.dumps({'status':conf_status,'method':'EMPIRICAL_BINNING_LAPLACE','calibration_set_size':len(dev_pairs),'evaluation_set':'SEALED_HOLDOUT','evaluation':ce,'bins':cal.bins,'note':'small fixture calibration; not a production probability guarantee'},indent=2))
 accepted=[r for r in rows if r['optimization_accepted']]
 (out/'QUALITY_ADJUSTED_TOKEN_REPORT.json').write_text(json.dumps({'claim_level':'ESTIMATED_SYNTHETIC_CONTEXT_REDUCTION','provider_token_reduction':'UNVERIFIED','accepted_runs':len(accepted),'mean_estimated_context_reduction_pct':statistics.mean(r['estimated_context_reduction_pct'] for r in accepted) if accepted else None,'engineering_task_success':'UNVERIFIED_REAL_AGENT','rule':'token reduction is not provider saving and is accepted here only when critical retrieval context is preserved'},indent=2))
 (out/'CONTEXT_RANKING_REPORT.json').write_text(json.dumps({'mean_mrr':report['mean_mrr'],'mean_ndcg_at_5':report['mean_ndcg_at_5'],'runs':[{'id':r['id'],'target_rank':r['target_rank'],'mrr':r['mrr'],'ndcg_at_5':r['ndcg_at_5']} for r in rows]},indent=2))
 (out/'CONTEXT_MARGINAL_VALUE.json').write_text(json.dumps({'evaluation_side_analysis':True,'runs':[{'id':r['id'],'selected':[{'path':p,'required_by_hidden_evaluator':p in set(GT[r['id']]['required'])} for p in r['selected']]} for r in rows]},indent=2))
 langs={lang:{'target_accuracy':sum(r['target_ok'] for r in rows if r['lang']==lang)/sum(1 for r in rows if r['lang']==lang),'critical_recall':min(r['critical_recall'] for r in rows if r['lang']==lang),'mean_precision':statistics.mean(r['precision'] for r in rows if r['lang']==lang)} for lang in sorted(set(r['lang'] for r in rows))};(out/'MULTILINGUAL_GENERALIZATION_REPORT.json').write_text(json.dumps(langs,indent=2,ensure_ascii=False))
 return {**report,'confidence':ce,'language':langs}
if __name__=='__main__':print(json.dumps(run(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2,ensure_ascii=False))
