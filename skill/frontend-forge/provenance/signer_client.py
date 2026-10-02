import multiprocessing as mp,secrets
from .signer_service import signer_loop
from .ipc_protocol import make_request
class SignerClient:
 def __init__(self,state_dir,broker_id,secret):
  self.secret=secret;self.broker_id=broker_id;parent,child=mp.Pipe();self.conn=parent;self.proc=mp.Process(target=signer_loop,args=(child,state_dir,broker_id,secret),daemon=True);self.proc.start()
 def sign(self,record):self.conn.send(make_request(self.secret,self.broker_id,record));return self.conn.recv()
 def close(self):
  try:self.conn.send({'op':'STOP'})
  except Exception:pass
  self.proc.join(timeout=1)
