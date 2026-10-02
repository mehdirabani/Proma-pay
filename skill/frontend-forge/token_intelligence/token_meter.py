from dataclasses import dataclass
import re

@dataclass
class TokenCount:
    tokens:int
    status:str
    method:str

class DeterministicTokenEstimator:
    """Model-agnostic conservative estimator. Never reports MEASURED."""
    def estimate(self,text):
        if text is None:text=''
        # Blend character and lexical estimates to reduce pathological undercounting.
        chars=max(1,(len(text)+3)//4) if text else 0
        words=len(re.findall(r"\w+|[^\w\s]",text,flags=re.UNICODE))
        lexical=(words*4+2)//3
        return TokenCount(max(chars,lexical), 'ESTIMATED', 'deterministic-char-lexical-v1')

def meter(parts,estimator=None):
    est=estimator or DeterministicTokenEstimator()
    out={}
    total=0
    for name,text in parts.items():
        c=est.estimate(text);out[name]={'tokens':c.tokens,'status':c.status,'method':c.method};total+=c.tokens
    out['total_tokens']={'tokens':total,'status':'ESTIMATED','method':est.estimate('').method}
    return out
