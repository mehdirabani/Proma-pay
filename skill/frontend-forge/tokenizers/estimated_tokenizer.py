import re
from .base_tokenizer import BaseTokenizer,TokenMeasurement
class EstimatedTokenizer(BaseTokenizer):
    def __init__(self,model=None):self.model=model
    def count(self,text):
        text=text or ''
        chars=(len(text)+3)//4
        lexical=(len(re.findall(r"\w+|[^\w\s]",text,flags=re.UNICODE))*4+2)//3
        state='ESTIMATED_MODEL_SPECIFIC' if self.model else 'ESTIMATED_GENERIC'
        return TokenMeasurement(max(chars,lexical),state,'char-lexical-v2'+(f':{self.model}' if self.model else ''))
