import base64,hashlib,hmac,json,multiprocessing as mp,os,secrets,threading,time
from pathlib import Path
from .keys.file_key_provider import FileKeyProvider

def canonical(x):return json.dumps(x,sort_keys=True,separators=(",",":"),ensure_ascii=False).encode()
def _mac(secret,body):return hmac.new(secret,canonical(body),hashlib.sha256).hexdigest()
DEFAULT_ACL={
 'engineering-observation-broker':{'engineering_observation','engineering_evidence'},
 'execution-broker':{'tool_execution','execution_receipt'},
 'change-broker':{'engineering_change'},
 'validation-broker':{'engineering_validation'},
}
def _loop(conn,state_dir,registry):
 key=FileKeyProvider(state_dir);seen=set()
 while True:
  m=conn.recv()
  if m.get('op')=='STOP':break
  bid=m.get('broker_id');entry=registry.get(bid)
  if not entry:conn.send({'ok':False,'error':'UNREGISTERED_BROKER'});continue
  body={k:v for k,v in m.items() if k!='request_mac'}
  if not hmac.compare_digest(_mac(entry['secret'],body),str(m.get('request_mac'))):conn.send({'ok':False,'error':'BROKER_AUTH_FAILED'});continue
  if abs(time.time()-float(m.get('requested_at',0)))>30:conn.send({'ok':False,'error':'STALE_REQUEST'});continue
  nonce=m.get('nonce')
  if nonce in seen:conn.send({'ok':False,'error':'REPLAY_REJECTED'});continue
  seen.add(nonce);record=dict(m.get('record') or {});rt=record.get('record_type')
  if rt not in set(entry['allowed']):conn.send({'ok':False,'error':'BROKER_ACL_DENIED'});continue
  record['authority_broker_id']=bid;record['key_id']=key.key_id();record['key_status']=key.metadata()['status']
  sig=base64.b64encode(key.sign(canonical(record))).decode();conn.send({'ok':True,'record':{**record,'signature':sig}})

class BrokerClient:
 def __init__(self,svc,broker_id,secret):self.svc=svc;self.broker_id=broker_id;self.secret=secret
 def sign(self,record):
  body={'op':'SIGN','broker_id':self.broker_id,'nonce':secrets.token_hex(16),'requested_at':time.time(),'record':record}
  msg={**body,'request_mac':_mac(self.secret,body)}
  with self.svc.lock:self.svc.conn.send(msg);return self.svc.conn.recv()

class CentralAuthorityService:
 def __init__(self,state_dir,acl=None):
  self.state_dir=str(Path(state_dir).resolve());self.lock=threading.Lock();self.registry={}
  for bid,allowed in (acl or DEFAULT_ACL).items():self.registry[bid]={'secret':os.urandom(32),'allowed':sorted(allowed)}
  parent,child=mp.Pipe();self.conn=parent;self.proc=mp.Process(target=_loop,args=(child,self.state_dir,self.registry),daemon=True);self.proc.start()
 def broker(self,broker_id):
  if broker_id not in self.registry:raise PermissionError('UNREGISTERED_BROKER')
  return BrokerClient(self,broker_id,self.registry[broker_id]['secret'])
 def close(self):
  try:
   with self.lock:self.conn.send({'op':'STOP'})
  except Exception:pass
  self.proc.join(timeout=1)

_services={};_services_lock=threading.Lock()
def get_central_authority(state_dir=None):
 root=str(Path(state_dir or os.environ.get('FFX_RUNTIME_STATE_HOME',Path.home()/'.frontend-forge-x')).resolve())
 with _services_lock:
  svc=_services.get(root)
  if svc is None or not svc.proc.is_alive():svc=CentralAuthorityService(root);_services[root]=svc
  return svc

def close_all():
 with _services_lock:
  for svc in list(_services.values()):svc.close()
  _services.clear()
