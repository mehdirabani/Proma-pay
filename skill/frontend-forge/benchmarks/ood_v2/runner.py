from pathlib import Path
import json,tempfile,statistics,math,sys,time
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT))
from .fixture import generate
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.open_set import OpenSetTargetDiscovery
from retrieval.confidence_v2 import OpenSetConfidenceModel
from retrieval.context_minimality import minimum_evidence_set
from token_intelligence.token_meter import DeterministicTokenEstimator
BASE=Path(__file__).parent
CAL=json.loads((BASE/'calibration_tasks.json').read_text());CGT=json.loads((BASE/'calibration_ground_truth.json').read_text())
OOD=json.loads((BASE/'ood_public_tasks.json').read_text());OGT=json.loads((BASE/'ood_sealed_ground_truth.json').read_text())

def fit_model(idx):
    base=OpenSetTargetDiscovery(idx);records=[]
    for t in CAL:
        r=base.discover(t['task']);gt=CGT[t['id']];pred=r['targets'][0]['path'] if r['targets'] else None
        ok=(pred==gt['target']) if gt['target'] is not None else (r['state']=='TARGET_NOT_FOUND')
        label='EXACT' if ok and gt['target'] is not None else 'NOT_FOUND_CORRECTLY' if ok else 'WRONG'
        records.append({'score':r.get('raw_confidence',0),'label':label,'target_present':gt['target'] is not None,'retrieval_score':r.get('retrieval_score',0)})
    return OpenSetConfidenceModel().fit(records,bins=12),records

def _ctx(index,target,limit=6):
    n=index['nodes'].get(target,{})
    cand=[{'path':target,'reason':'target','graph_distance':0,'selected_because':['target']}]
    for rel in n.get('styles',[]):cand.append({'path':rel,'reason':'style','graph_distance':1,'selected_because':['style']})
    for rel in n.get('tests',[]):cand.append({'path':rel,'reason':'test','graph_distance':1,'selected_because':['test']})
    for rel in n.get('imports',[]):
        if rel in index['nodes']:cand.append({'path':rel,'reason':'import','graph_distance':1,'selected_because':['behavior']})
    for rel in n.get('reverse_imports',[])[:2]:cand.append({'path':rel,'reason':'reverse_import','graph_distance':1,'selected_because':['consumer']})
    return minimum_evidence_set(cand,critical_paths=[target])['selected'][:limit]

def run(outdir):
    out=Path(outdir);out.mkdir(parents=True,exist_ok=True)
    with tempfile.TemporaryDirectory() as d:
        repo=generate(Path(d)/'repo');idx=RepositoryIndexer(repo).scan();model,cal_records=fit_model(idx);eng=OpenSetTargetDiscovery(idx,model)
        rows=[];tok=DeterministicTokenEstimator()
        for t in OOD:
            r=eng.discover(t['task']);gt=OGT[t['id']];pred=r['targets'][0]['path'] if r['targets'] and r['state']!='TARGET_NOT_FOUND' else None
            correct=(pred==gt['target']) if gt['target'] is not None else (r['state']=='TARGET_NOT_FOUND')
            if gt['target'] is not None and pred:
                context=_ctx(idx,pred);selected=[x['path'] for x in context]
                required={gt['target']};precision=len(required&set(selected))/max(1,len(selected));recall=len(required&set(selected))/len(required)
                et=sum(tok.estimate((repo/p).read_text(errors='ignore')).tokens for p in selected if (repo/p).exists())
            else:selected=[];precision=1.0 if gt['target'] is None and correct else 0.0;recall=1.0 if gt['target'] is None and correct else 0.0;et=0
            rows.append({'id':t['id'],'lang':t['lang'],'expected_target':gt['target'],'predicted_target':pred,'state':r['state'],'correct':correct,'confidence':r['confidence'],'retrieval_score':r.get('retrieval_score',0),'precision':precision,'critical_recall':recall,'estimated_context_tokens':et})
        positives=[r for r in rows if r['expected_target'] is not None];opens=[r for r in rows if r['expected_target'] is None]
        acc=sum(r['correct'] for r in rows)/len(rows);target_acc=sum(r['correct'] for r in positives)/len(positives);abst=sum(r['correct'] for r in opens)/len(opens)
        high=[r for r in rows if r['confidence']>=model.thresholds['resolve']];hcw=sum(1 for r in high if not r['correct'])/max(1,len(high))
        # risk/coverage curve
        curve=[]
        for th in [i/20 for i in range(5,20)]:
            auto=[r for r in rows if r['confidence']>=th and r['state']=='TARGET_RESOLVED']
            curve.append({'threshold':th,'coverage':round(len(auto)/len(rows),4),'accuracy':round(sum(x['correct'] for x in auto)/max(1,len(auto)),4),'n':len(auto)})
        cal_eval=model.evaluate(cal_records)
        report={'benchmark':'retrieval-benchmark-v2','measurement':'SEALED_OOD_SYNTHETIC','cases':len(rows),'overall_accuracy':acc,'target_accuracy':target_acc,'abstention_accuracy':abst,'high_confidence_wrong_rate':hcw,'mean_precision':statistics.mean(r['precision'] for r in positives),'critical_recall':min(r['critical_recall'] for r in positives),'thresholds':model.thresholds,'calibration':cal_eval,'runs':rows}
        (out/'OOD_RETRIEVAL_REPORT.json').write_text(json.dumps(report,indent=2,ensure_ascii=False))
        (out/'OPEN_SET_REPORT.json').write_text(json.dumps({'open_set_cases':len(opens),'abstention_accuracy':abst,'false_positive_rate':round(1-abst,4),'cases':opens},indent=2,ensure_ascii=False))
        (out/'CONFIDENCE_RISK_REPORT.json').write_text(json.dumps({'high_confidence_wrong_rate':hcw,'thresholds':model.thresholds,'calibration':cal_eval},indent=2))
        (out/'RISK_COVERAGE_CURVE.json').write_text(json.dumps({'curve':curve},indent=2))
        (out/'CONTEXT_PRECISION_REPORT.json').write_text(json.dumps({'mean_precision':report['mean_precision'],'critical_recall':report['critical_recall'],'zero_marginal_value_ratio':'MEASURED_PER_CONTEXT_SET','note':'minimum-evidence selection used for OOD positive cases'},indent=2))
        langs={lang:{'cases':len([r for r in rows if r['lang']==lang]),'accuracy':sum(r['correct'] for r in rows if r['lang']==lang)/max(1,len([r for r in rows if r['lang']==lang])),'target_accuracy':sum(r['correct'] for r in positives if r['lang']==lang)/max(1,len([r for r in positives if r['lang']==lang]))} for lang in sorted(set(r['lang'] for r in rows))}
        (out/'MULTILINGUAL_OOD_REPORT.json').write_text(json.dumps(langs,indent=2,ensure_ascii=False))
        return report
if __name__=='__main__':print(json.dumps(run(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2,ensure_ascii=False))
