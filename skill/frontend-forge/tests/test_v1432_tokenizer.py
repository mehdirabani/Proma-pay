import unittest
from tokenizers.estimated_tokenizer import EstimatedTokenizer
from tokenizers.provider_tokenizer import ProviderTokenizer,ProviderUsageMeasurement
class Tokenizer(unittest.TestCase):
    def test_generic_estimate_state(self):self.assertEqual(EstimatedTokenizer().count('hello').state,'ESTIMATED_GENERIC')
    def test_model_estimate_state(self):self.assertEqual(EstimatedTokenizer('x').count('hello').state,'ESTIMATED_MODEL_SPECIFIC')
    def test_real_tokenizer_adapter_state(self):self.assertEqual(ProviderTokenizer(lambda s:[1,2,3],'x').count('a').state,'MEASURED_TOKENIZER')
    def test_provider_usage_unverified_without_fields(self):self.assertEqual(ProviderUsageMeasurement.from_usage({})['state'],'UNVERIFIED')
