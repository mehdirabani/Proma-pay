from dataclasses import dataclass, asdict
import subprocess, os, signal, time, threading, uuid, sys
from pathlib import Path
from security.sandbox_policy import SandboxPolicy, detect_sandbox_backend
from security.environment_policy import sanitized_env
from security.secret_detector import redact

try:
    import resource
except Exception:
    resource=None

@dataclass
class ProcessResult:
    status:str
    execution_id:str
    argv:list
    exit_code:int|None=None
    stdout:str=""
    stderr:str=""
    error:str|None=None
    duration_ms:float=0.0
    sandbox_backend:str|None=None
    isolation_level:str="NONE"
    def to_dict(self): return asdict(self)

class CancellationToken:
    def __init__(self): self._event=threading.Event()
    def cancel(self): self._event.set()
    @property
    def cancelled(self): return self._event.is_set()

class ProcessRegistry:
    def __init__(self): self._lock=threading.Lock(); self._procs={}; self._meta={}; self._cancelled=set()
    def register(self,eid,p,session_id=None,capability_id=None):
        with self._lock:
            self._procs[eid]=p
            self._meta[eid]={'session_id':session_id,'capability_id':capability_id,'pid':p.pid,'started_at':time.time(),'status':'RUNNING'}
    def unregister(self,eid):
        with self._lock:
            self._procs.pop(eid,None)
            if eid in self._meta:self._meta[eid]['status']='FINISHED'
    def running(self):
        with self._lock:return sorted(self._procs)
    def running_for_session(self,session_id):
        with self._lock:return sorted(e for e in self._procs if self._meta.get(e,{}).get('session_id')==session_id)
    def cancel_session(self,session_id):
        ids=self.running_for_session(session_id)
        return {eid:self.cancel(eid) for eid in ids}
    def was_cancelled(self,eid):
        with self._lock:return eid in self._cancelled
    def clear_cancelled(self,eid):
        with self._lock:self._cancelled.discard(eid)
    def cancel(self,eid,grace=0.5):
        with self._lock:
            p=self._procs.get(eid)
            if p:self._cancelled.add(eid)
        if not p:return False
        _terminate_process_group(p,grace)
        return True

PROCESS_REGISTRY=ProcessRegistry()

def _limits(policy):
    if resource is None or os.name!='posix': return None
    def apply():
        pairs=[]
        for name,val in [
            ('RLIMIT_CPU',policy.max_cpu_seconds),
            ('RLIMIT_AS',policy.max_memory_mb*1024*1024),
            ('RLIMIT_FSIZE',policy.max_file_size_mb*1024*1024),
            ('RLIMIT_NOFILE',policy.max_open_files),
            ('RLIMIT_NPROC',policy.max_processes),
        ]:
            if hasattr(resource,name):
                r=getattr(resource,name); pairs.append((r,val))
        for r,val in pairs:
            try: resource.setrlimit(r,(val,val))
            except (ValueError,OSError): pass
    return apply

def _terminate_process_group(p,grace=0.5):
    if p.poll() is not None:return
    try:
        if os.name=='posix': os.killpg(p.pid,signal.SIGTERM)
        else: p.terminate()
    except ProcessLookupError:return
    try:p.wait(timeout=grace)
    except subprocess.TimeoutExpired:
        try:
            if os.name=='posix': os.killpg(p.pid,signal.SIGKILL)
            else:p.kill()
        except ProcessLookupError:pass
        try:p.wait(timeout=1)
        except Exception:pass

class ProcessSupervisor:
    def __init__(self, registry=None): self.registry=registry or PROCESS_REGISTRY
    def run(self,argv,policy:SandboxPolicy,timeout=None,mode='LOCAL',env=None,cancellation_token=None,execution_id=None,actor='process',issue_receipt=False,session_id=None,capability_id=None):
        eid=execution_id or str(uuid.uuid4()); timeout=float(timeout or policy.default_timeout)
        workspace=policy.workspace()
        require_full = policy.require_full_isolation and policy.trust_level=='untrusted' and mode in {'LOCAL','CI','PRODUCTION'}
        backend=detect_sandbox_backend(require_full=require_full)
        if backend is None:
            return ProcessResult('SANDBOX_UNAVAILABLE',eid,list(argv),error='full isolation backend unavailable',sandbox_backend=None,isolation_level='NONE').to_dict()
        wrapped=backend.wrap(argv,policy)
        proc_env=sanitized_env(env or os.environ)
        start=time.monotonic()
        try:
            p=subprocess.Popen(wrapped,cwd=str(workspace),stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,
                shell=False,env=proc_env,start_new_session=True,preexec_fn=_limits(policy))
        except Exception as e:
            return ProcessResult('EXECUTION_FAILURE',eid,list(argv),error=redact(str(e)),sandbox_backend=backend.name,isolation_level=backend.isolation_level).to_dict()
        self.registry.register(eid,p,session_id=session_id,capability_id=capability_id)
        status=None; out=''; err=''; error=None
        try:
            while True:
                if cancellation_token is not None and cancellation_token.cancelled:
                    _terminate_process_group(p); status='CANCELLED'; error='cancelled'; break
                elapsed=time.monotonic()-start
                if elapsed>=timeout:
                    _terminate_process_group(p); status='TOOL_TIMEOUT'; error='timeout'; break
                try:
                    out,err=p.communicate(timeout=min(0.1,max(0.01,timeout-elapsed)))
                    status='CANCELLED' if self.registry.was_cancelled(eid) else ('PASS' if p.returncode==0 else 'FAIL'); break
                except subprocess.TimeoutExpired: continue
            if status in {'CANCELLED','TOOL_TIMEOUT'}:
                try:
                    o,e=p.communicate(timeout=0.2);out+=o or '';err+=e or ''
                except Exception: pass
        finally:
            self.registry.unregister(eid)
        
        clean_out=redact(out);clean_err=redact(err)
        result=ProcessResult(status,eid,list(argv),p.returncode,clean_out,clean_err,error,round((time.monotonic()-start)*1000,2),backend.name,backend.isolation_level).to_dict()
        return result
