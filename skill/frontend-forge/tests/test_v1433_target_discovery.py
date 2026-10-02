import unittest,tempfile
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
from retrieval.target_discovery import TargetDiscoveryEngine
class TargetTests(unittest.TestCase):
 def repo(self,d):
  r=Path(d);(r/'src/components').mkdir(parents=True);(r/'src/pages').mkdir(parents=True);(r/'src/components/ProductCard.tsx').write_text("export function ProductCard(){return <article aria-label='product item'>Product</article>}")
  (r/'src/components/ProductCardLegacy.tsx').write_text("export function ProductCardLegacy(){return null}")
  (r/'src/pages/Search.tsx').write_text("import {ProductCard} from '../components/ProductCard'; export const Search=()=> <ProductCard/>")
  return r
 def test_explicit(self):
  with tempfile.TemporaryDirectory() as d:
   idx=RepositoryIndexer(self.repo(d)).scan();x=TargetDiscoveryEngine(idx).discover('Fix ProductCard overflow');self.assertEqual(x['targets'][0]['path'],'src/components/ProductCard.tsx')
 def test_implicit_english(self):
  with tempfile.TemporaryDirectory() as d:
   idx=RepositoryIndexer(self.repo(d)).scan();x=TargetDiscoveryEngine(idx).discover('Fix the mobile overflow in the product tile');self.assertEqual(x['targets'][0]['path'],'src/components/ProductCard.tsx')
 def test_implicit_persian(self):
  with tempfile.TemporaryDirectory() as d:
   idx=RepositoryIndexer(self.repo(d)).scan();x=TargetDiscoveryEngine(idx).discover('رفع بیرون زدگی کارت محصول در موبایل');self.assertEqual(x['targets'][0]['path'],'src/components/ProductCard.tsx')
