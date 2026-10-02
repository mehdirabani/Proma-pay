import unittest,tempfile,json
from pathlib import Path
from benchmarks.generalization.fixture import generate
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
from retrieval.two_stage import TwoStageRetriever
class Holdout(unittest.TestCase):
 def setUp(self):self.t=tempfile.TemporaryDirectory();self.repo=generate(Path(self.t.name)/'r');self.idx=RepositoryIndexer(self.repo).scan();self.e=TargetDiscoveryEngine(self.idx)
 def tearDown(self):self.t.cleanup()
 def test_unseen_catalog_phrase(self):self.assertEqual(self.e.discover('The catalog entry spills outside the phone viewport')['targets'][0]['path'],'src/components/MerchandisePanel.tsx')
 def test_persian_holdout(self):self.assertEqual(self.e.discover('کادر کالا تو صفحه گوشی جا نمی شود')['targets'][0]['path'],'src/components/MerchandisePanel.tsx')
 def test_purchase_form_not_auth(self):self.assertEqual(self.e.discover('The purchase form cannot be tabbed')['targets'][0]['path'],'src/components/PurchasePane.tsx')
 def test_header_links(self):self.assertEqual(self.e.discover('Header links overlap on a narrow viewport')['targets'][0]['path'],'src/components/TopLinks.tsx')
 def test_expansion_does_not_blind_pass(self):
  r=TwoStageRetriever(self.repo,self.idx).retrieve('something completely unrelated zxqv');self.assertNotEqual(r.get('state'),'CONTEXT_SUFFICIENT')
