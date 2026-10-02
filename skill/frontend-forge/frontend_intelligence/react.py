import re
def analyze_react(text):
    findings=[]
    states=re.findall(r'const\s*\[([^,]+),\s*(set\w+)\]\s*=\s*useState',text)
    effects=len(re.findall(r'\buseEffect\s*\(',text))
    if effects>4:findings.append({'kind':'effect_overuse','count':effects})
    if re.search(r'useEffect\([^)]*set\w+.*\[[^\]]*\]',text,re.S):findings.append({'kind':'derived_state_or_effect_write_review'})
    if re.search(r'\.map\([^)]*=>\s*<[^>]+key=\{?index',text):findings.append({'kind':'unstable_key'})
    return {'states':[x[0].strip() for x in states],'effects':effects,'findings':findings}
