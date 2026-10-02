import unittest,tempfile,hashlib
from pathlib import Path
from token_intelligence.context_cache import FileSummaryCache
from token_intelligence.memory import MemoryStore
class T(unittest.TestCase):
 def test_cache_invalidates(self):
  with tempfile.TemporaryDirectory() as d:
   p=Path(d)/'a.ts';p.write_text('one');c=FileSummaryCache(Path(d)/'c.json');c.put(p,'summary');self.assertIsNotNone(c.get(p));p.write_text('two');self.assertIsNone(c.get(p))
 def test_memory_promotion(self):
  with tempfile.TemporaryDirectory() as d:
   m=MemoryStore(Path(d)/'m.json');x=m.add_candidate('project','framework','react',True,True,True,'h');self.assertEqual(x['status'],'PROJECT_READY');self.assertIsNotNone(m.valid('project','framework','h'));self.assertIsNone(m.valid('project','framework','other'))
