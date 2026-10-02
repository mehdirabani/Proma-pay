import unittest,tempfile
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from repository_intelligence.context_candidate_builder import ContextCandidateBuilder
from token_intelligence.context_selector import select
class T(unittest.TestCase):
 def test_100_similar_files_do_not_hide_exact_target(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);r.mkdir(exist_ok=True)
   (r/'ProductCard.tsx').write_text('export const ProductCard=1')
   for i in range(100):(r/f'ProductCardCopy{i}.tsx').write_text(f'export const ProductCardCopy{i}=1')
   idx=RepositoryIndexer(r).scan();rows=ContextCandidateBuilder(r,idx).build('Change ProductCard.tsx padding');res=select(rows,'Change ProductCard.tsx padding',100)
   self.assertEqual(res['loaded'][0]['path'],'ProductCard.tsx')
 def test_selector_source_has_no_hidden_ground_truth_name(self):
  import inspect,token_intelligence.context_selector as cs
  self.assertNotIn('hidden_evaluation',inspect.getsource(cs))
