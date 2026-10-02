import unittest
from token_intelligence.relevance_engine import score,excluded
class T(unittest.TestCase):
 def test_direct_target_p0(self):self.assertEqual(score({'path':'src/Button.tsx','targets':['src/Button.tsx']},'button')['priority'],'P0')
 def test_generated_excluded(self):self.assertTrue(excluded('dist/app.min.js','fix css')[0])
 def test_lockfile_context_sensitive(self):self.assertTrue(excluded('package-lock.json','change button')[0]);self.assertFalse(excluded('package-lock.json','dependency audit')[0])
