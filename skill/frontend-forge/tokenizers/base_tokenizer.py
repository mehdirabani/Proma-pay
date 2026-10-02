from dataclasses import dataclass
@dataclass
class TokenMeasurement:
    tokens:int
    state:str
    method:str
class BaseTokenizer:
    def count(self,text):raise NotImplementedError
