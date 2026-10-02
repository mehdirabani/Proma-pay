from dataclasses import dataclass
from pathlib import Path
import shutil

@dataclass
class SandboxPolicy:
    workspace_root:str
    network_mode:str="DENY"  # DENY | ALLOWLIST | FULL
    network_allowlist:tuple=()
    network_allowed:bool|None=None  # v14.1 compatibility
    max_processes:int=256
    max_memory_mb:int=1536
    max_cpu_seconds:int=120
    max_file_size_mb:int=128
    max_open_files:int=256
    default_timeout:int=120
    trust_level:str="PROJECT_UNTRUSTED"
    require_full_isolation:bool=True
    def __post_init__(self):
        if self.network_allowed is not None:
            self.network_mode = "FULL" if self.network_allowed else "DENY"
    def workspace(self): return Path(self.workspace_root).resolve()

class SandboxBackend:
    name="base"; isolation_level="NONE"
    def available(self): return False
    def wrap(self,argv,policy): return list(argv)

class BubblewrapBackend(SandboxBackend):
    name="bubblewrap"; isolation_level="FILESYSTEM_NETWORK"
    def available(self): return shutil.which("bwrap") is not None
    def wrap(self,argv,policy):
        root=policy.workspace()
        cmd=["bwrap","--die-with-parent","--unshare-user","--unshare-pid","--unshare-ipc","--unshare-uts"]
        if policy.network_mode=="DENY":cmd+=["--unshare-net"]
        for system in ("/usr","/bin","/lib","/lib64","/etc"):
            if Path(system).exists():cmd+=["--ro-bind",system,system]
        cmd+=["--bind",str(root),str(root),"--tmpfs","/tmp","--dev","/dev","--proc","/proc","--chdir",str(root),"--"]
        return cmd+list(argv)

class ContainerBackend(SandboxBackend):
    name="container"; isolation_level="FILESYSTEM_NETWORK"
    def __init__(self):
        self.runtime=shutil.which("docker") or shutil.which("podman")
    def available(self): return self.runtime is not None
    def wrap(self,argv,policy):
        # Generic image is intentionally not auto-selected; availability only unless configured.
        raise RuntimeError("container image not configured")

class ProcessOnlyBackend(SandboxBackend):
    name="process-only"; isolation_level="PROCESS_RESOURCE"
    def available(self): return True
    def wrap(self,argv,policy): return list(argv)

def detect_sandbox_backend(require_full=False):
    for b in (BubblewrapBackend(),):
        if b.available(): return b
    if require_full:return None
    return ProcessOnlyBackend()

def sandbox_report(policy):
    b=detect_sandbox_backend(require_full=policy.require_full_isolation)
    return {"full_isolation_available":bool(b and b.isolation_level=="FILESYSTEM_NETWORK"),
            "backend":b.name if b else None,"network_mode":policy.network_mode}
