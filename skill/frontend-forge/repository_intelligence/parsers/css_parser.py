import re
from .base_code_parser import BaseCodeParser
class CSSParser(BaseCodeParser):
    mode='REDUCED'
    def parse(self,text,path):
        imports=re.findall(r'@import\s+[\'\"]([^\'\"]+)',text)
        symbols=re.findall(r'\.([A-Za-z_][\w-]*)\s*[{,]',text)
        return {'mode':self.mode,'imports':imports,'exports':[],'symbols':sorted(set(symbols))}
