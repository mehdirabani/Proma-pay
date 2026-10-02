import base64,time,json,hashlib
from .ipc_protocol import verify,canonical
from .keys.file_key_provider import FileKeyProvider
def signer_loop(conn,state_dir,broker_id,secret):
 p=FileKeyProvider(state_dir);nonces=set();signed=set()
 while True:
  m=conn.recv()
  if m.get('op')=='STOP':break
  if m.get('broker_id')!=broker_id or not verify(secret,m):conn.send({'ok':False,'error':'ACCESS_DENIED'});continue
  if abs(time.time()-float(m.get('requested_at',0)))>30:conn.send({'ok':False,'error':'STALE_REQUEST'});continue
  n=m.get('request_nonce');r=m.get('record',{});eid=r.get('execution_id')
  if n in nonces:conn.send({'ok':False,'error':'REPLAY_REJECTED'});continue
  nonces.add(n)
  if not eid:conn.send({'ok':False,'error':'INVALID_EXECUTION_RECORD'});continue
  if eid in signed:conn.send({'ok':False,'error':'REPLAY_DETECTED'});continue
  if r.get('requested_capability_id') and r.get('requested_capability_id')!=r.get('capability_id'):conn.send({'ok':False,'error':'CAPABILITY_IDENTITY_MISMATCH'});continue
  signed.add(eid);payload={**r,'key_id':p.key_id(),'key_status':p.metadata()['status']};sig=base64.b64encode(p.sign(canonical(payload))).decode();conn.send({'ok':True,'record':{**payload,'signature':sig}})
