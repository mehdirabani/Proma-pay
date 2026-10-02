import time
def run_with_retry(fn,max_attempts=2,backoff="none"):
    last=None
    for attempt in range(1,max_attempts+1):
        try:return {"status":"PASS","attempts":attempt,"result":fn()}
        except Exception as e:
            last=e
            if attempt<max_attempts and backoff=="exponential":time.sleep(min(0.01*(2**(attempt-1)),0.05))
    return {"status":"FAIL","attempts":max_attempts,"error":str(last)}
