import re
def analyze_css(text):
    findings=[]
    if re.search(r'white-space\s*:\s*nowrap',text) and re.search(r'width|min-width',text):findings.append({'kind':'overflow_risk','signals':['nowrap','width']})
    if len(re.findall(r'!important',text))>2:findings.append({'kind':'important_abuse'})
    z=[int(x) for x in re.findall(r'z-index\s*:\s*(\d+)',text)]
    if z and max(z)>999:findings.append({'kind':'z_index_escalation','max':max(z)})
    return {'findings':findings}
