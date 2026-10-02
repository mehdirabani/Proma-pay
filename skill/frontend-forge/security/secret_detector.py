import re
PATTERNS=[
re.compile(r'(?i)["\']?(api[_-]?key|token|password|secret)["\']?\s*[:=]\s*["\']?([A-Za-z0-9_\-./+=]{8,})["\']?'),
re.compile(r'(?i)(authorization:\s*bearer)\s+([A-Za-z0-9._\-]+)'),
re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----.*?-----END (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',re.S)
]
SENSITIVE_KEYS={"api_key","apikey","token","password","secret","authorization","cookie"}
def redact(text):
    out=str(text)
    for p in PATTERNS:
        if p.groups>=2:
            out=p.sub(lambda m:f"{m.group(1)}=[REDACTED]",out)
        else:
            out=p.sub("[REDACTED_PRIVATE_KEY]",out)
    return out
def redact_data(obj):
    if isinstance(obj,dict):
        out={}
        for k,v in obj.items():
            if str(k).lower().replace("-","_") in SENSITIVE_KEYS:
                out[k]="[REDACTED]"
            else:
                out[k]=redact_data(v)
        return out
    if isinstance(obj,list): return [redact_data(x) for x in obj]
    if isinstance(obj,str): return redact(obj)
    return obj
