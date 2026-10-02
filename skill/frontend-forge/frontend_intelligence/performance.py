import re
def analyze_performance(text):
    findings=[]
    imports=re.findall(r'import\s+.*?from\s+[\'\"]([^\'\"]+)',text)
    heavy=[x for x in imports if x in {'lodash','moment','three','chart.js'}]
    if heavy:findings.append({'kind':'large_dependency_review','dependencies':heavy})
    if text.count('useMemo(')>6:findings.append({'kind':'memoization_overuse_review'})
    return {'findings':findings,'measurement_required':bool(findings)}
