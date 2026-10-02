from pathlib import Path
class PathPolicyError(ValueError): pass

def resolve_in_workspace(workspace,path,must_exist=False):
    root=Path(workspace).resolve()
    target=(root/path).resolve() if not Path(path).is_absolute() else Path(path).resolve()
    try: target.relative_to(root)
    except ValueError: raise PathPolicyError("path escapes workspace")
    if must_exist and not target.exists(): raise PathPolicyError("path does not exist")
    return target

class WorkspacePathPolicy:
    def __init__(self,workspace): self.root=Path(workspace).resolve()
    def resolve(self,path,must_exist=False): return resolve_in_workspace(self.root,path,must_exist)
