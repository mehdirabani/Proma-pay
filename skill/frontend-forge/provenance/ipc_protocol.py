import hmac,hashlib,json,time,secrets
def canonical(x):return json.dumps(x,sort_keys=True,separators=(",",":")).encode()
def mac(secret,payload):return hmac.new(secret,canonical(payload),hashlib.sha256).hexdigest()
def make_request(secret,broker_id,record):
 body={'broker_id':broker_id,'request_nonce':secrets.token_hex(16),'requested_at':time.time(),'record':record}
 return {**body,'request_mac':mac(secret,body)}
def verify(secret,msg):
 body={k:v for k,v in msg.items() if k!='request_mac'}
 return hmac.compare_digest(mac(secret,body),str(msg.get('request_mac')))
