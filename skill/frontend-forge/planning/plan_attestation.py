import hashlib,json
def canonical_hash(x):return hashlib.sha256(json.dumps(x,sort_keys=True,separators=(",",":"),ensure_ascii=False,default=str).encode()).hexdigest()
def attest_plan(plan):return {**plan,'plan_hash':canonical_hash(plan)}
