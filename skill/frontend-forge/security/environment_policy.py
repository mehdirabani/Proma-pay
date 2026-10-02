SAFE_ENV_KEYS={'PATH','LANG','LC_ALL','NODE_ENV','CI','TERM'}
SECRET_HINTS=('KEY','TOKEN','SECRET','PASSWORD','COOKIE','AUTH')
def sanitized_env(env):
    out={}
    for k,v in env.items():
        if any(h in k.upper() for h in SECRET_HINTS):continue
        if k in SAFE_ENV_KEYS:out[k]=v
    out['HOME']='/tmp'
    return out
