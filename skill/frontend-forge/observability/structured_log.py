from datetime import datetime,timezone
from security.secret_detector import redact_data
def log_record(level,session_id,component,event,extra=None):
    rec={"level":level,"session_id":session_id,"component":component,"event":event,"timestamp":datetime.now(timezone.utc).isoformat()}
    if extra:rec["extra"]=redact_data(extra)
    return rec
