CLASSES=('TRIVIAL','SMALL','MEDIUM','LARGE','REPOSITORY_WIDE')
def classify(task,changed_files=0,repo_files=0):
    t=(task or '').lower()
    if any(x in t for x in ['repository-wide','monorepo','entire repository']):return 'REPOSITORY_WIDE'
    if any(x in t for x in ['architecture','migration','large refactor']) or changed_files>30:return 'LARGE'
    if any(x in t for x in ['refactor','feature','checkout','debug']) or changed_files>5:return 'MEDIUM'
    if any(x in t for x in ['component','responsive','accessibility','performance']) or changed_files>1:return 'SMALL'
    return 'TRIVIAL'

def graph_depth(complexity):return {'TRIVIAL':1,'SMALL':1,'MEDIUM':2,'LARGE':3,'REPOSITORY_WIDE':4}[complexity]
