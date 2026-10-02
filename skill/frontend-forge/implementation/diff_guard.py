import re
ANTI_PATTERNS=[
 ('typescript_any',r'\bas\s+any\b|:\s*any\b'),('ts_ignore',r'@ts-ignore'),('eslint_disable',r'eslint-disable'),
 ('important_abuse',r'!important'),('reload_workaround',r'location\.reload\('),('timeout_workaround',r'setTimeout\s*\(')
]
def inspect_diff(changes,budget=None,allowed_files=None):
    files={c['path'] for c in changes};lines=sum(max(c.get('lines_changed',0),0) for c in changes)
    unrelated=sorted(files-set(allowed_files or files))
    flags=[]
    for c in changes:
        for name,pat in ANTI_PATTERNS:
            if re.search(pat,c.get('after','')) and not re.search(pat,c.get('before','')):flags.append({'path':c['path'],'flag':name})
    exceeded=[]
    if budget:
        if len(files)>budget.get('max_files',999):exceeded.append('max_files')
        if lines>budget.get('max_lines',10**9):exceeded.append('max_lines')
    status='UNRELATED_CHANGE_DETECTED' if unrelated else 'CHANGE_BUDGET_EXCEEDED' if exceeded else 'PASS'
    return {'status':status,'files_changed':len(files),'lines_changed':lines,'unrelated_changes':unrelated,'anti_pattern_flags':flags,'budget_exceeded':exceeded}
