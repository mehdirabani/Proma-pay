from pathlib import Path
import tempfile,json,statistics,sys,hashlib
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT))
from repository_intelligence.repo_indexer import RepositoryIndexer
from repository_intelligence.context_candidate_builder import ContextCandidateBuilder
from token_intelligence.context_selector import select
from token_intelligence.concurrent_cache import ConcurrentSummaryCache
from token_intelligence.token_meter import DeterministicTokenEstimator
from tokenizers.estimated_tokenizer import EstimatedTokenizer
from token_intelligence.regression_gate import accept_optimization,saving_percentage
from hidden_eval import evaluate
from structured_repo import generate
SCENARIOS=json.loads((Path(__file__).parent/'public_scenarios.json').read_text())
HIDDEN=json.loads((Path(__file__).parent/'hidden_ground_truth.json').read_text())

def _tokens(rows):
    est=DeterministicTokenEstimator();return sum(est.estimate(x.get('text','')).tokens for x in rows)
def naive(candidates,task,limit=20):
    words=[w.lower() for w in task.replace('/',' ').split() if len(w)>3];sc=[]
    for c in candidates:
        s=sum(w in (c['path']+' '+c.get('text','')[:500]).lower() for w in words);sc.append((s,c))
    return [x[1] for x in sorted(sc,key=lambda x:-x[0])[:limit]]
def run_blind(repo,scenario,hidden,cache=None,warm=False):
    index=RepositoryIndexer(repo).scan();builder=ContextCandidateBuilder(repo,index)
    # Selector receives only task + repository-derived candidates. Hidden evaluation stays below boundary.
    candidates=builder.build(scenario['task'])
    baseline=naive(candidates,scenario['task'],limit={'SMALL':18,'MEDIUM':30,'LARGE':60}.get(scenario['complexity'],12))
    selected=select(candidates,scenario['task'],budget_tokens=max(180,int(_tokens(baseline)*.55)),confidence=.8)
    opt=selected['loaded']
    baseq=evaluate([x['path'] for x in baseline],hidden);optq=evaluate([x['path'] for x in opt],hidden)
    bt,ot=_tokens(baseline),_tokens(opt)
    accepted=ot<bt and optq['task_success']>=baseq['task_success'] and optq['critical_dependency_recall']>=baseq['critical_dependency_recall']
    return {'scenario':scenario['id'],'measurement':'ESTIMATED_TOKEN_USAGE','measurement_state':'ESTIMATED_GENERIC','baseline_strategy':'naive-neighborhood-v2','optimized_strategy':'forge-v14.3.2','baseline_tokens':bt,'optimized_tokens':ot,'saving_percentage':saving_percentage(bt,ot),'baseline_quality':baseq,'optimized_quality':optq,'quality_delta':round((optq['critical_dependency_recall']-baseq['critical_dependency_recall'])*100,2),'accepted':accepted,'selected_paths':[x['path'] for x in opt]}
def main(outdir):
    outdir=Path(outdir);outdir.mkdir(parents=True,exist_ok=True);runs=[];scale=[]
    with tempfile.TemporaryDirectory() as d:
        for n in [50,100,500,1000]:
            repo=generate(Path(d)/f'r{n}',n=n,seed=42);scenario=SCENARIOS[0];r=run_blind(repo,scenario,HIDDEN[scenario['id']]);
            idx=RepositoryIndexer(repo).scan();allrows=ContextCandidateBuilder(repo,idx).build(scenario['task']);full_tokens=_tokens(allrows);lex=naive(allrows,scenario['task'],limit=min(n, max(20,int(n*.08))));
            scale.append({'repo_files':n,'full_repository_tokens':full_tokens,'lexical_baseline_tokens':_tokens(lex),**r})
        repo=generate(Path(d)/'realistic',n=160,seed=7)
        for s in SCENARIOS:runs.append(run_blind(repo,s,HIDDEN[s['id']]))
        # Real persisted cold/warm cache accounting.
        index=RepositoryIndexer(repo).scan();cache=ConcurrentSummaryCache(Path(d)/'cache.db','fixture')
        init_cost=0;warm_saved=0
        for rel,node in list(index['nodes'].items())[:30]:
            text=(repo/rel).read_text(errors='ignore');init_cost+=EstimatedTokenizer().count(text).tokens;summary=f"{rel}: symbols={','.join(node.get('symbols',[])[:5])}; imports={','.join(node.get('imports',[])[:5])}";cache.put(rel,text,summary)
        warm_cost=0
        for rel,node in list(index['nodes'].items())[:30]:
            text=(repo/rel).read_text(errors='ignore');item=cache.get(rel,text);warm_cost+=EstimatedTokenizer().count(item['summary'] if item else text).tokens
        longitudinal=[];base_per=max(1,statistics.mean([x['baseline_tokens'] for x in runs]));opt_per=max(1,statistics.mean([x['optimized_tokens'] for x in runs]));
        for n in [1,5,10,20,50]:longitudinal.append({'tasks':n,'baseline_cumulative':round(base_per*n,2),'forge_cumulative':round(init_cost+opt_per*n,2),'forge_cheaper':init_cost+opt_per*n<base_per*n})
    report={'claim_level':'ESTIMATED_FIXTURE','measurement':'ESTIMATED_TOKEN_USAGE','measurement_state':'ESTIMATED_GENERIC','oracle_free':True,'runs':runs,'external_real_repo_benchmark':'UNVERIFIED','real_agent_benchmark':'UNVERIFIED'}
    (outdir/'TOKEN_EFFICIENCY_REPORT.json').write_text(json.dumps(report,indent=2))
    (outdir/'REAL_TOKEN_USAGE_REPORT.json').write_text(json.dumps({'LLM_TOKEN_BENCHMARK':'UNVERIFIED','reason':'no provider usage metadata/tokenizer available'},indent=2))
    (outdir/'CONTEXT_PRECISION_RECALL.json').write_text(json.dumps({'runs':[{'scenario':x['scenario'],**x['optimized_quality']} for x in runs]},indent=2))
    (outdir/'REPOSITORY_SCALE_REPORT.json').write_text(json.dumps({'measurement':'ESTIMATED_GENERIC','runs':scale},indent=2))
    (outdir/'COLD_WARM_COMPARISON.json').write_text(json.dumps({'measurement':'ESTIMATED_GENERIC','initialization_tokens':init_cost,'cold_analysis_tokens':init_cost,'warm_summary_tokens':warm_cost,'warm_reduction_after_initialization':saving_percentage(init_cost,warm_cost)},indent=2))
    (outdir/'LONGITUDINAL_COST_REPORT.json').write_text(json.dumps({'measurement':'ESTIMATED_GENERIC','initialization_cost':init_cost,'runs':longitudinal},indent=2))
    be=next((x['tasks'] for x in longitudinal if x['forge_cheaper']),None);(outdir/'BREAK_EVEN_REPORT.json').write_text(json.dumps({'measurement':'ESTIMATED_GENERIC','cumulative_break_even_tasks':be},indent=2))
    (outdir/'CONTEXT_CORRECTNESS_REPORT.json').write_text(json.dumps({'critical_dependency_recall_gate':1.0,'all_pass':all(x['optimized_quality']['critical_dependency_recall']==1.0 for x in runs),'runs':runs},indent=2))
    (outdir/'TOKEN_REGRESSION_REPORT.json').write_text(json.dumps({'status':'PASS' if all(x['accepted'] for x in runs) else 'REVIEW','rejected':[x['scenario'] for x in runs if not x['accepted']]},indent=2))
    (outdir/'TOKEN_OPTIMIZATION_CONTRIBUTIONS.json').write_text(json.dumps({'status':'UNVERIFIED','reason':'ablation contribution not claimed without sufficiently large maintained task set'},indent=2))
    return report
if __name__=='__main__':print(json.dumps(main(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2))
