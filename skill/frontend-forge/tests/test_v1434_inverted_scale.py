import unittest,tempfile
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from repository_intelligence.inverted_index import InvertedIndex
class Inv(unittest.TestCase):
 def test_preselection(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src').mkdir();
   for i in range(300):(r/'src'/f'F{i}.ts').write_text(f'export const F{i}={i}')
   idx=RepositoryIndexer(r).scan();rows,m=InvertedIndex(idx).preselect('change F299');self.assertIn('src/F299.ts',rows);self.assertLess(m['index_nodes_considered'],300)
