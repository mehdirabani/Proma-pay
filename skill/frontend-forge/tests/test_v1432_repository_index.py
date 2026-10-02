import unittest,tempfile
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from repository_intelligence.context_candidate_builder import ContextCandidateBuilder
from repository_intelligence.graph_store import GraphStore
class RepoIndex(unittest.TestCase):
    def fixture(self,d):
        r=Path(d);(r/'src').mkdir();(r/'tests').mkdir();(r/'src/A.ts').write_text("import {b} from './B'\nexport const a=b\n");(r/'src/B.ts').write_text('export const b=1\n');(r/'tests/A.test.ts').write_text("import {a} from '../src/A'\n")
        return r
    def test_auto_build_reverse_import(self):
        with tempfile.TemporaryDirectory() as d:
            r=self.fixture(d);idx=RepositoryIndexer(r).scan();self.assertIn('src/B.ts',idx['nodes']['src/A.ts']['imports']);self.assertIn('src/A.ts',idx['nodes']['src/B.ts']['reverse_imports'])
    def test_incremental_update(self):
        with tempfile.TemporaryDirectory() as d:
            r=self.fixture(d);ix=RepositoryIndexer(r);idx=ix.scan();old=idx['nodes']['src/B.ts']['hash'];(r/'src/B.ts').write_text('export const b=2\n');idx2=ix.update(idx,['src/B.ts']);self.assertNotEqual(old,idx2['nodes']['src/B.ts']['hash']);self.assertEqual(idx2['changed'],['src/B.ts'])
    def test_graph_persistence(self):
        with tempfile.TemporaryDirectory() as d:
            r=self.fixture(d);idx=RepositoryIndexer(r).scan();s=GraphStore(Path(d)/'g.db');v=s.save(idx);self.assertEqual(v,1);self.assertEqual(s.load()['index']['workspace_id'],idx['workspace_id'])
    def test_candidate_builder_no_hidden_arg(self):
        with tempfile.TemporaryDirectory() as d:
            r=self.fixture(d);idx=RepositoryIndexer(r).scan();b=ContextCandidateBuilder(r,idx);rows=b.build('change A');self.assertTrue(rows);self.assertFalse(any('hidden_evaluation' in x for x in rows))
