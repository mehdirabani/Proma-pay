from .base_tokenizer import BaseTokenizer,TokenMeasurement
class ProviderTokenizer(BaseTokenizer):
    def __init__(self,encoder,model):self.encoder=encoder;self.model=model
    def count(self,text):return TokenMeasurement(len(self.encoder(text or '')),'MEASURED_TOKENIZER',f'provider-tokenizer:{self.model}')
class ProviderUsageMeasurement:
    @staticmethod
    def from_usage(usage):
        fields={k:usage[k] for k in ['input_tokens','output_tokens','cached_input_tokens','reasoning_tokens'] if k in usage}
        if not fields:return {'state':'UNVERIFIED','usage':{}}
        return {'state':'MEASURED_PROVIDER','usage':fields}
