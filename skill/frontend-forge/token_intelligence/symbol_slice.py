import re

def extract_symbols(text):
    patterns=[r'(?:export\s+)?(?:async\s+)?function\s+(\w+)',r'(?:export\s+)?class\s+(\w+)',r'(?:export\s+)?interface\s+(\w+)',r'(?:export\s+)?const\s+(\w+)\s*=']
    out=[]
    for pat in patterns:out.extend(re.findall(pat,text))
    return sorted(set(out))
def structure_index(text):return {'symbols':extract_symbols(text),'lines':text.count('\n')+1,'bytes':len(text.encode())}
