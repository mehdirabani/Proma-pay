import unittest,tempfile
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
class Confidence(unittest.TestCase):
 def test_no_caller_confidence_parameter(self):
  import inspect;sig=inspect.signature(TargetDiscoveryEngine.discover);self.assertNotIn('confidence',sig.parameters)
 def test_ambiguous_target_not_blind_pass(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src').mkdir();(r/'src/A.ts').write_text('export const A=1');idx=RepositoryIndexer(r).scan();x=TargetDiscoveryEngine(idx).discover('something unrelated');self.assertEqual(x['status'],'AMBIGUOUS_TARGET')
