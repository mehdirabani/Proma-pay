import unittest
from routing.intent_normalizer import normalize_intent
from token_intelligence.module_loader import select_modules,module_cost
class Routing(unittest.TestCase):
    def test_persian_accessibility(self):self.assertIn('accessibility',normalize_intent('دسترسی‌پذیری این دکمه را اصلاح کن')['domains'])
    def test_persian_performance(self):self.assertIn('performance',normalize_intent('سرعت لود ProductCard را بهتر کن')['domains'])
    def test_persian_tailwind(self):self.assertIn('css-tailwind',normalize_intent('فاصله این بخش را در Tailwind کم کن')['domains'])
    def test_fast_signal(self):self.assertIn('performance',normalize_intent('Make ProductCard load faster')['domains'])
    def test_spacing_not_ci(self):self.assertNotIn('ci-internals',select_modules('fix Tailwind spacing')['loaded_modules'])
    def test_module_cost_dynamic(self):
        c=module_cost('core-router');self.assertGreater(c['tokens'],0);self.assertIn(c['measurement_state'],{'ESTIMATED_GENERIC','ESTIMATED_MODEL_SPECIFIC','MEASURED_TOKENIZER'})
