import tempfile,json,sys,os
from pathlib import Path
import jsonschema
from .io_utils import load_json
from adapters.registry import get_adapter
from security.process_supervisor import ProcessSupervisor
from security.sandbox_policy import SandboxPolicy

class CapabilityError(RuntimeError):pass

def _validate_outputs(cap,result):
    if not isinstance(result,dict):return False,'result must be object'
    contracts=load_json('runtime/output-contracts.json')
    for name in cap.get('outputs',[]):
        if name not in result:return False,f'missing declared output:{name}'
        schema=contracts.get(name)
        if schema:
            try:jsonschema.validate(result[name],schema)
            except jsonschema.ValidationError as e:return False,f'output schema failed:{name}:{e.message}'
    return True,None

def execute_capability(capability_id,context,mode='LOCAL'):
    reg=load_json('runtime/capability-registry.json')['capabilities']
    if capability_id not in reg:raise CapabilityError(f'unknown capability: {capability_id}')
    c=reg[capability_id];missing=[x for x in c.get('inputs',[]) if x not in context]
    if missing:raise CapabilityError(f'missing inputs:{missing}')
    if c['type']=='agent':raise CapabilityError('agent capabilities require AgentHost')
    timeout=c.get('timeout',60)
    if c['type']=='adapter':
        adapter_context=dict(context);adapter_context.setdefault('capability_id',capability_id)
        raw=get_adapter(c['adapter']).execute(adapter_context,mode=mode,timeout=timeout)
        # Adapter execution status is preserved; declared output always wraps the adapter report.
        result={c['outputs'][0]:raw} if len(c.get('outputs',[]))==1 else {'adapter_result':raw}
        ok,err=_validate_outputs(c,result)
        return {'status':raw.get('status','FAIL') if ok else 'INVALID_CAPABILITY_OUTPUT','capability':capability_id,'result':result if ok else None,'validation_error':err}
    if c['type']=='system':raise CapabilityError('system capability cannot recursively execute itself')
    # Engine functions execute in a killable subprocess, not a worker thread.
    with tempfile.TemporaryDirectory() as d:
        d=Path(d);inp=d/'in.json';out=d/'out.json'; control_token=context.get('cancellation_token'); serial={k:v for k,v in context.items() if k not in {'cancellation_token','execution_id'}};inp.write_text(json.dumps(serial),encoding='utf-8')
        root=Path(__file__).resolve().parents[1]
        policy=SandboxPolicy(str(root),trust_level='trusted',require_full_isolation=False,max_cpu_seconds=max(2,int(timeout)+1),max_processes=256,default_timeout=int(timeout))
        env=dict(os.environ);env['PYTHONPATH']=str(root)+os.pathsep+env.get('PYTHONPATH','')
        cmd=[sys.executable,'-m','runtime_py.engine_worker','--handler',c['handler'],'--input',str(inp),'--output',str(out)]
        pr=ProcessSupervisor().run(cmd,policy,timeout=timeout,mode='LOCAL',env=env,execution_id=context.get('execution_id'),cancellation_token=control_token,actor=capability_id)
        if pr['status']!='PASS':return {'status':pr['status'],'capability':capability_id,'result':None,'process':pr}
        try:payload=json.loads(out.read_text(encoding='utf-8'))
        except Exception as e:return {'status':'INVALID_CAPABILITY_OUTPUT','capability':capability_id,'result':None,'validation_error':f'worker output unreadable:{e}'}
        if not payload.get('ok'):return {'status':'EXECUTION_FAILURE','capability':capability_id,'result':None,'validation_error':payload.get('error')}
        result=payload.get('result');ok,err=_validate_outputs(c,result)
        return {'status':'PASS' if ok else 'INVALID_CAPABILITY_OUTPUT','capability':capability_id,'result':result if ok else None,'validation_error':err,'process':pr}
