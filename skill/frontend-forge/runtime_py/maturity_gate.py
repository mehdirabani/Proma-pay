from .io_utils import load_json
READY={"VERIFIED","PRODUCTION_READY"}
def ensure_capability_ready(capability_id,mode="LOCAL",required=True):
 r=load_json("runtime/capability-registry.json")["capabilities"]
 if capability_id not in r:return {"status":"CAPABILITY_NOT_READY","reason":"unknown"}
 m=r[capability_id].get("maturity","STUB")
 if mode=="PRODUCTION" and required and m not in READY:return {"status":"CAPABILITY_NOT_READY","maturity":m}
 return {"status":"PASS","maturity":m}
