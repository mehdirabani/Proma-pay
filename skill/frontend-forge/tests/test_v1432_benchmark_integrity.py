import unittest,tempfile,importlib.util,sys,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class BenchmarkIntegrity(unittest.TestCase):
    def load_runner(self):
        d=ROOT/'benchmarks/token-efficiency';sys.path.insert(0,str(d));spec=importlib.util.spec_from_file_location('br',d/'benchmark_runner.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
    def test_blind_run_and_reports(self):
        m=self.load_runner()
        with tempfile.TemporaryDirectory() as d:
            r=m.main(d);self.assertTrue(r['oracle_free']);self.assertEqual(r['real_agent_benchmark'],'UNVERIFIED');self.assertTrue(Path(d,'CONTEXT_CORRECTNESS_REPORT.json').exists())
    def test_hidden_ground_truth_not_candidate_metadata(self):
        m=self.load_runner()
        with tempfile.TemporaryDirectory() as d:
            repo=m.generate(Path(d)/'r',50);idx=__import__('repository_intelligence.repo_indexer',fromlist=['RepositoryIndexer']).RepositoryIndexer(repo).scan();b=__import__('repository_intelligence.context_candidate_builder',fromlist=['ContextCandidateBuilder']).ContextCandidateBuilder(repo,idx);rows=b.build(m.SCENARIOS[0]['task']);self.assertFalse(any('hidden_evaluation' in x or 'required_context' in x for x in rows))
    def test_quality_loss_rejected(self):
        from token_intelligence.regression_gate import accept_optimization
        self.assertFalse(accept_optimization(1000,100,100,60))
