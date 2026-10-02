from pathlib import Path
import re,collections

def mine_patterns(workspace):
    root=Path(workspace);files=[p for p in root.rglob('*') if p.is_file() and p.suffix in {'.ts','.tsx','.js','.jsx','.css'}]
    quote=collections.Counter();tests=[];api=[];forms=[]
    for p in files[:500]:
        t=p.read_text(errors='ignore')
        quote['single']+=t.count("'");quote['double']+=t.count('"')
        if re.search(r'describe\(|it\(|test\(',t):tests.append(str(p.relative_to(root)))
        if re.search(r'fetch\(|axios\.',t):api.append(str(p.relative_to(root)))
        if '<form' in t:forms.append(str(p.relative_to(root)))
    preferred='single' if quote['single']>=quote['double'] else 'double'
    return {'files_scanned':len(files),'quote_style':preferred,'testing_examples':tests[:5],'api_examples':api[:5],'form_examples':forms[:5]}
