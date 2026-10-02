import unittest, tempfile, json, time
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
from benchmarks.cost_accounting import CostLedger

ROOT=Path(__file__).resolve().parents[1]

class BenchmarkIntegrity1433(unittest.TestCase):
    def test_production_retrieval_does_not_reference_hidden_ground_truth(self):
        for rel in ['retrieval/target_discovery.py','retrieval/two_stage.py','repository_intelligence/context_candidate_builder.py']:
            text=(ROOT/rel).read_text().lower()
            self.assertNotIn('hidden_ground_truth',text)
            self.assertNotIn('hidden_evaluation',text)
    def test_5000_file_index_completes(self):
        with tempfile.TemporaryDirectory() as d:
            r=Path(d);(r/'src').mkdir()
            for i in range(5000):
                dep=f"import {{F{i-1}}} from './F{i-1}'\n" if i and i%7==0 else ''
                (r/'src'/f'F{i}.ts').write_text(dep+f'export const F{i}={i}')
            start=time.perf_counter();idx=RepositoryIndexer(r).scan();elapsed=time.perf_counter()-start
            self.assertEqual(len(idx['nodes']),5000)
            self.assertLess(elapsed,30)
    def test_local_bytes_cannot_be_provider_tokens(self):
        l=CostLedger();l.add('local_runtime','read',10000,'bytes','filesystem');l.add('llm','input',100,'provider_tokens','provider')
        totals=l.totals();self.assertEqual(len(totals),2);self.assertNotEqual(totals[0]['unit'],totals[1]['unit'])
    def test_real_agent_report_is_unverified_without_provider(self):
        p=ROOT/'reports/REAL_AGENT_BENCHMARK.json'
        if p.exists():self.assertEqual(json.loads(p.read_text())['status'],'UNVERIFIED')
