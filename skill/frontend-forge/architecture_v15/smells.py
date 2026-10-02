import re

def detect(files):
    findings=[]
    for path,text in files.items():
        loc=len(text.splitlines())
        if loc>500:findings.append({'path':path,'smell':'god_component','evidence':{'lines':loc}})
        if text.count('useContext(')>3:findings.append({'path':path,'smell':'excessive_context_coupling'})
        if len(re.findall(r'\.\./\.\./',text))>4:findings.append({'path':path,'smell':'boundary_leakage'})
    return findings
