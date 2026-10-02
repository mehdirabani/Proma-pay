import unittest,tempfile,threading
from token_intelligence.concurrent_cache import ConcurrentSummaryCache
from token_intelligence.memory_v2 import MemoryEntry
class CacheMemory(unittest.TestCase):
    def test_real_cold_warm(self):
        with tempfile.TemporaryDirectory() as d:
            c=ConcurrentSummaryCache(d+'/c.db','w');self.assertIsNone(c.get('a.ts','one'));c.put('a.ts','one','sum');self.assertEqual(c.get('a.ts','one')['summary'],'sum')
    def test_stale_cache(self):
        with tempfile.TemporaryDirectory() as d:
            c=ConcurrentSummaryCache(d+'/c.db','w');c.put('a.ts','one','sum');self.assertIsNone(c.get('a.ts','two'))
    def test_concurrent_cache_writes(self):
        with tempfile.TemporaryDirectory() as d:
            c=ConcurrentSummaryCache(d+'/c.db','w');errs=[]
            def f(i):
                try:c.put(f'{i}.ts',str(i),f's{i}')
                except Exception as e:errs.append(str(e))
            ts=[threading.Thread(target=f,args=(i,)) for i in range(20)];[t.start() for t in ts];[t.join() for t in ts];self.assertEqual(errs,[])
    def test_memory_dependency_invalidation(self):
        m=MemoryEntry('x','v',['a'],{'a':'h1'},'r1',1);self.assertEqual(m.validate({'a':'h2'},'r1',1),'STALE')
