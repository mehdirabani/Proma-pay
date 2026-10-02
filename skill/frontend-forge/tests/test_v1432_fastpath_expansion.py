import unittest,tempfile
from pathlib import Path
from routing.path_router import choose_path
from repository_intelligence.repo_indexer import RepositoryIndexer
from repository_intelligence.query import RepositoryQuery
from token_intelligence.context_expansion import expand
class T(unittest.TestCase):
 def test_trivial_fast_path(self):self.assertEqual(choose_path('TRIVIAL','LOW',.9)['path'],'FAST_PATH')
 def test_high_risk_deep_path(self):self.assertEqual(choose_path('TRIVIAL','HIGH',.9)['path'],'DEEP_PATH')
 def test_deterministic_importer_query(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'A.ts').write_text("import {b} from './B'\n");(r/'B.ts').write_text('export const b=1')
   idx=RepositoryIndexer(r).scan();self.assertIn('A.ts',RepositoryQuery(idx).importers('B.ts'))
 def test_three_hop_expansion(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'A.ts').write_text("import {b} from './B'\n");(r/'B.ts').write_text("import {c} from './C'\nexport const b=c");(r/'C.ts').write_text("import {d} from './D'\nexport const c=d");(r/'D.ts').write_text('export const d=1')
   idx=RepositoryIndexer(r).scan();x=expand(idx,['A.ts'],3);self.assertIn('D.ts',x['paths'])
