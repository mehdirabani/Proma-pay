import unittest,tempfile
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from repository_intelligence.context_candidate_builder import ContextCandidateBuilder
from retrieval.two_stage import TwoStageRetriever
class TwoStage(unittest.TestCase):
 def test_metadata_does_not_load_content(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src').mkdir();(r/'src/Button.tsx').write_text('export const Button=()=>null');(r/'src/Other.ts').write_text('export const Other=1')
   idx=RepositoryIndexer(r).scan();m=ContextCandidateBuilder(r,idx).build_metadata('change Button');self.assertTrue(all(not x['content_loaded'] for x in m))
 def test_only_shortlist_opened(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src').mkdir();
   for i in range(40):(r/'src'/f'F{i}.ts').write_text(f'export const F{i}=1')
   (r/'src'/'Button.tsx').write_text("export const Button=()=> <button aria-label='save button'>x</button>")
   idx=RepositoryIndexer(r).scan();x=TwoStageRetriever(r,idx).retrieve('change save button',budget_candidates=5);self.assertLessEqual(x['io_metrics']['files_opened'],5);self.assertGreater(x['io_metrics']['files_stat_ed'],x['io_metrics']['files_opened'])
