from dataclasses import dataclass,field,asdict
from datetime import datetime,timezone
import uuid
def now(): return datetime.now(timezone.utc).isoformat()
@dataclass
class Session:
    task_id:str
    session_id:str=field(default_factory=lambda:str(uuid.uuid4()))
    state:str="TASK_RECEIVED"
    created_at:str=field(default_factory=now)
    updated_at:str=field(default_factory=now)
    attempt:int=1
    mode:str="LOCAL"
    active_capabilities:list=field(default_factory=list)
    artifacts:list=field(default_factory=list)
    failures:list=field(default_factory=list)
    context:dict=field(default_factory=dict)
    def to_dict(self): return asdict(self)
