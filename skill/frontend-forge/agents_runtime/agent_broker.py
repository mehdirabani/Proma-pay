import multiprocessing as mp,time,hashlib,json,uuid
import jsonschema
from provenance.execution_broker import ExecutionBroker
from provenance.verifier import verify_signed

def _worker(handler,task,context,q):
    try:q.put({'ok':True,'output':handler(task,context)})
    except Exception as e:q.put({'ok':False,'error':repr(e)})
class AgentExecutionBroker:
    def __init__(self,handlers,schemas=None):self.handlers=handlers;self.schemas=schemas or {};self._broker=ExecutionBroker();self._authority=self._broker._authority
    def close(self):self._broker.close()
    def execute(self,agent,task,context,*,timeout=30,cancel_token=None,retries=0,session_id='agent-session',task_id='agent-task'):
        if agent not in self.handlers:return {'status':'UNIMPLEMENTED','agent':agent}
        attempt=0
        while True:
            attempt+=1;q=mp.Queue();start=time.time();p=mp.Process(target=_worker,args=(self.handlers[agent],task,context,q));p.start()
            while p.is_alive() and time.time()-start<timeout:
                if cancel_token is not None and cancel_token.cancelled:p.terminate();p.join();return {'status':'CANCELLED','agent':agent,'attempt':attempt}
                time.sleep(.02)
            if p.is_alive():
                p.terminate();p.join()
                if attempt<=retries:continue
                return {'status':'TOOL_TIMEOUT','agent':agent,'attempt':attempt}
            if q.empty():return {'status':'FAIL','agent':agent,'attempt':attempt}
            r=q.get()
            if not r['ok']:return {'status':'FAIL','agent':agent,'error':r['error'],'attempt':attempt}
            out=r['output'];schema=self.schemas.get(agent)
            if schema:
                try:jsonschema.validate(out,schema)
                except jsonschema.ValidationError as e:return {'status':'INVALID_CAPABILITY_OUTPUT','agent':agent,'error':e.message}
            oh=hashlib.sha256(json.dumps(out,sort_keys=True,separators=(',',':')).encode()).hexdigest()
            receipt=self._authority.sign_receipt({'receipt_id':str(uuid.uuid4()),'session_id':session_id,'task_id':task_id,'execution_id':str(uuid.uuid4()),
              'capability_id':f'agent:{agent}','adapter_id':'agent-host','actor':agent,'runtime_mode':'LOCAL','workspace_id':'agent','workspace_fingerprint':'agent',
              'project_revision':context.get('project_revision','UNKNOWN'),'command_hash':hashlib.sha256(agent.encode()).hexdigest(),'tool_binary_hash':None,'tool_version':'agent-host-v1',
              'sandbox_backend':'agent-process','sandbox_policy_hash':'agent-process','started_at':start,'completed_at':time.time(),'exit_code':0,'stdout_hash':oh,'stderr_hash':hashlib.sha256(b'').hexdigest(),'status':'PASS'})
            return {'status':'PASS','agent':agent,'output':out,'attempt':attempt,'execution_receipt':receipt}
