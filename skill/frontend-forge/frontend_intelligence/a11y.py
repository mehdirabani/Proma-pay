import re
def analyze_a11y(text):
    findings=[]
    for m in re.finditer(r'<input\b([^>]*)>',text,re.I):
        attrs=m.group(1)
        if not re.search(r'aria-label|aria-labelledby|id=',attrs):findings.append({'kind':'input_label_review'})
    if re.search(r'<div[^>]+onClick=',text) and not re.search(r'role=|tabIndex=',text):findings.append({'kind':'nonsemantic_click_target'})
    return {'findings':findings,'principle':'semantic_html_first'}
