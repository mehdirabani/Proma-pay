import unittest,tempfile,time
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
class Scale(unittest.TestCase):
 def test_1000_index_completes(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src').mkdir();
   for i in range(1000):(r/'src'/f'F{i}.ts').write_text((f"import {{F{i-1}}} from './F{i-1}'\n" if i else '')+f'export const F{i}={i}')
   t=time.perf_counter();idx=RepositoryIndexer(r).scan();self.assertEqual(len(idx['nodes']),1000);self.assertLess(time.perf_counter()-t,20)
