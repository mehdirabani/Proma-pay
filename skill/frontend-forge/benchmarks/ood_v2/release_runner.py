from pathlib import Path
import json,tempfile,statistics,sys,math
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT))
from .fixture import generate
from .runner import fit_model
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.open_set import OpenSetTargetDiscovery
from retrieval.context_minimality import minimum_evidence_set
from token_intelligence.token_meter import DeterministicTokenEstimator
BASE=Path(__file__).parent
TASKS=json.loads((BASE/'release_public_tasks.json').read_text());GT=json.loads((BASE/'release_sealed_ground_truth.json').read_text())
def dcg(xs):return sum(v/math.log2(i+2) for i,v in enumerate(xs))
def run(outdir):
 out=Path(outdir);out.mkdir(parents=True,exist_ok=True)
 with tempfile.TemporaryDirectory() as d:
  repo=generate(Path(d)/'repo');idx=RepositoryIndexer(repo).scan();model,_=fit_model(idx);eng=OpenSetTargetDiscovery(idx,model);est=DeterministicTokenEstimator();rows=[]
  for t in TASKS:
   r=eng.discover(t['task']);g=GT[t['id']];rank=[x['path'] for x in r['targets']];pred=rank[0] if rank and r['state']!='TARGET_NOT_FOUND' else None;target_ok=(pred==g['target']) if g['target'] else r['state']=='TARGET_NOT_FOUND'
   if g['target'] is None:selected=[]
   else:
    seed_count=1 if r['state']=='TARGET_RESOLVED' else min(3,len(rank));cands=[]
    for target in rank[:seed_count]:
      if target not in idx['nodes']:continue
      n=idx['nodes'][target];cands.append({'path':target,'reason':'target','graph_distance':0,'selected_because':['target']})
      for rel in n.get('styles',[]):cands.append({'path':rel,'reason':'style','graph_distance':1,'selected_because':['style']})
      for rel in n.get('tests',[]):cands.append({'path':rel,'reason':'test','graph_distance':1,'selected_because':['test']})
    selected=[x['path'] for x in minimum_evidence_set(cands,critical_paths=rank[:seed_count])['selected']]
   req=set(g['required']);precision=len(req&set(selected))/max(1,len(selected)) if req else (1.0 if target_ok else 0.0);recall=len(req&set(selected))/max(1,len(req)) if req else (1.0 if target_ok else 0.0);trank=(rank.index(g['target'])+1) if g['target'] in rank else None
   rows.append({'id':t['id'],'lang':t['lang'],'state':r['state'],'target_ok':target_ok,'predicted':pred,'expected':g['target'],'confidence':r['confidence'],'target_rank':trank,'precision':precision,'critical_recall':recall,'selected':selected,'estimated_context_tokens':sum(est.estimate((repo/p).read_text(errors='ignore')).tokens for p in selected if (repo/p).exists())})
 pos=[x for x in rows if x['expected']];opens=[x for x in rows if not x['expected']];high=[x for x in rows if x['confidence']>=model.thresholds['resolve'] and x['state']=='TARGET_RESOLVED']
 curve=[]
 for th in [0.5,0.6,0.7,0.8,0.85,0.9,0.95]:
  a=[x for x in rows if x['confidence']>=th and x['state']=='TARGET_RESOLVED'];curve.append({'threshold':th,'coverage':len(a)/len(rows),'accuracy':sum(x['target_ok'] for x in a)/max(1,len(a)),'n':len(a)})
 report={'benchmark_version':'retrieval-benchmark-v2-release-sealed','cases':len(rows),'target_accuracy':sum(x['target_ok'] for x in pos)/len(pos),'abstention_accuracy':sum(x['target_ok'] for x in opens)/len(opens),'overall_accuracy':sum(x['target_ok'] for x in rows)/len(rows),'mean_precision':statistics.mean(x['precision'] for x in pos),'mean_critical_recall':statistics.mean(x['critical_recall'] for x in pos),'minimum_critical_recall':min(x['critical_recall'] for x in pos),'mrr':statistics.mean((1/x['target_rank'] if x['target_rank'] else 0) for x in pos),'high_confidence_wrong_rate':sum(not x['target_ok'] for x in high)/max(1,len(high)),'thresholds':model.thresholds,'runs':rows}
 (out/'OOD_RETRIEVAL_REPORT.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));(out/'OPEN_SET_REPORT.json').write_text(json.dumps({'cases':len(opens),'abstention_accuracy':report['abstention_accuracy'],'runs':opens},indent=2,ensure_ascii=False));(out/'RISK_COVERAGE_CURVE.json').write_text(json.dumps({'curve':curve},indent=2));(out/'CONFIDENCE_RISK_REPORT.json').write_text(json.dumps({'high_confidence_wrong_rate':report['high_confidence_wrong_rate'],'thresholds':model.thresholds},indent=2));
 langs={lang:{'cases':len([x for x in rows if x['lang']==lang]),'target_accuracy':sum(x['target_ok'] for x in pos if x['lang']==lang)/max(1,len([x for x in pos if x['lang']==lang])),'abstention_accuracy':sum(x['target_ok'] for x in opens if x['lang']==lang)/max(1,len([x for x in opens if x['lang']==lang])),'mean_precision':statistics.mean(x['precision'] for x in pos if x['lang']==lang)} for lang in ['en','fa']};(out/'MULTILINGUAL_OOD_REPORT.json').write_text(json.dumps(langs,indent=2,ensure_ascii=False))
 return report
if __name__=='__main__':print(json.dumps(run(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2,ensure_ascii=False))
