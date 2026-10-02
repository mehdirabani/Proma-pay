import re
from .base_code_parser import BaseCodeParser
class HTMLParser(BaseCodeParser):
    mode='REDUCED'
    def parse(self,text,path):
        refs=re.findall(r'(?:src|href)=[\'\"]([^\'\"]+)',text)
        ids=re.findall(r'id=[\'\"]([^\'\"]+)',text)
        return {'mode':self.mode,'imports':refs,'exports':[],'symbols':ids}
