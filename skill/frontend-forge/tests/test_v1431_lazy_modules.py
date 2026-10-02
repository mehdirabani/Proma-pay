import unittest
from token_intelligence.module_loader import select_modules
class T(unittest.TestCase):
 def test_css_task_does_not_load_security_internals(self):
  r=select_modules('fix Tailwind spacing');self.assertIn('css-tailwind',r['loaded_modules']);self.assertNotIn('provenance-internals',r['loaded_modules']);self.assertNotIn('ci-internals',r['loaded_modules'])
 def test_high_risk_loads_security(self):self.assertIn('security',select_modules('change login',risk='HIGH')['loaded_modules'])
