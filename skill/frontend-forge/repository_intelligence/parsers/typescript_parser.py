import re
from .base_code_parser import BaseCodeParser
class TypeScriptParser(BaseCodeParser):
    mode='REDUCED'
    def parse(self,text,path):
        imports=[]
        for m in re.finditer(r"(?:import[^'\"]*from\s*|import\s*\(|require\s*\()\s*['\"]([^'\"]+)['\"]",text):imports.append(m.group(1))
        dynamic=re.findall(r'import\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)',text)
        reexports=re.findall(r'export\s+(?:\*|\{[^}]+\})\s+from\s+[\'\"]([^\'\"]+)[\'\"]',text)
        exports=re.findall(r'\bexport\s+(?:default\s+)?(?:const|let|var|function|class|interface|type|enum)\s+([A-Za-z_$][\w$]*)',text)
        symbols=re.findall(r'\b(?:function|class|interface|type|const|let|var)\s+([A-Za-z_$][\w$]*)',text)
        jsx=re.findall(r'<([A-Z][A-Za-z0-9_.]*)\b',text)
        strings=[x for x in re.findall(r'[\'\"]([^\'\"\n]{3,80})[\'\"]',text) if not x.startswith(('.', '@/'))]
        jsx_text=[x.strip() for x in re.findall(r'>([^<>{}\n]{2,100})<',text) if x.strip()]
        strings+=jsx_text
        aria=re.findall(r'aria-label\s*=\s*[\'\"]([^\'\"]+)',text)
        tests=re.findall(r'(?:test|it|describe)\s*\(\s*[\'\"]([^\'\"]+)',text)
        return {'mode':self.mode,'imports':sorted(set(imports+dynamic)),'reexports':sorted(set(reexports)),
                'exports':sorted(set(exports)),'symbols':sorted(set(symbols)),'jsx_components':sorted(set(jsx)),
                'string_literals':strings[:80],'aria_labels':aria[:40],'test_descriptions':tests[:40]}
