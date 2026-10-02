import re
from pathlib import Path

def analyze_source(path_or_text):
    p=Path(path_or_text) if isinstance(path_or_text,(str,Path)) else None
    text=p.read_text(encoding='utf-8',errors='ignore') if p and p.exists() else str(path_or_text)
    imports=re.findall(r'import\s+(?:type\s+)?(?:[^;]+?\s+from\s+)?[\'\"]([^\'\"]+)[\'\"]',text)
    exports=re.findall(r'export\s+(?:default\s+)?(?:function|class|const|let|var|interface|type)\s+([A-Za-z_$][\w$]*)',text)
    funcs=re.findall(r'(?:function\s+|const\s+)([A-Za-z_$][\w$]*)\s*(?:=\s*)?\(?',text)
    hooks=re.findall(r'\b(useState|useEffect|useMemo|useCallback|useContext|useReducer|use[A-Z][A-Za-z0-9_]*)\s*\(',text)
    jsx=re.findall(r'<([A-Z][A-Za-z0-9_.]*)\b',text)
    effects=[]
    for m in re.finditer(r'useEffect\s*\(\s*\(?.*?=>\s*\{(.*?)\}\s*,\s*\[(.*?)\]\s*\)',text,re.S):
        body,deps=m.group(1),[x.strip() for x in m.group(2).split(',') if x.strip()]
        effects.append({'dependencies':deps,'async':('await ' in body or '.then(' in body),'cleanup':('return ()' in body or 'return function' in body),'potential_loop':any(re.search(rf'\bset{re.escape(d[:1].upper()+d[1:])}\b',body) for d in deps if re.match(r'^\w+$',d))})
    return {'imports':sorted(set(imports)),'exports':sorted(set(exports)),'symbols':sorted(set(funcs+exports)),
            'hooks':hooks,'jsx_components':sorted(set(jsx)),'effects':effects,
            'mode':'REDUCED_STATIC','side_effect_signals':bool(re.search(r'localStorage|sessionStorage|fetch\(|axios\.|document\.|window\.',text))}
