import unittest
from token_intelligence.context_selector import select,minimum_sufficient_context
class T(unittest.TestCase):
 def test_overcontext_rejected(self):
  c=[{'path':'src/Button.tsx','text':'button','targets':['src/Button.tsx']}]+[{'path':f'src/X{i}.ts','text':'irrelevant'} for i in range(100)]
  r=select(c,'Change Button.tsx padding',100);self.assertEqual(r['loaded'][0]['path'],'src/Button.tsx');self.assertLess(len(r['loaded']),10)
 def test_low_confidence_expands(self):self.assertTrue(select([{'path':'x','text':'x','targets':['x']}],'x',50,confidence=.4)['needs_expansion'])
 def test_msc_preserves_quality(self):
  c=[{'path':'a','score':100},{'path':'b','score':20},{'path':'c','score':1}]
  def q(xs):return 100 if any(x['path']=='a' for x in xs) else 0
  r=minimum_sufficient_context(c,q);self.assertEqual([x['path'] for x in r['context']],['a'])
