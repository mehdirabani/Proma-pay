from abc import ABC,abstractmethod
from dataclasses import dataclass,asdict
@dataclass
class AdapterResult:
    status:str
    adapter:str
    command:list|None=None
    exit_code:int|None=None
    stdout:str=""
    stderr:str=""
    evidence:dict|None=None
    error:str|None=None
    def to_dict(self): return asdict(self)
class ToolAdapter(ABC):
    name="base"
    @abstractmethod
    def available(self): ...
    @abstractmethod
    def execute(self,context,mode="LOCAL",timeout=60): ...
    def validate(self,result):
        return result.get("status") in {"PASS","FAIL","TOOL_UNAVAILABLE","TOOL_TIMEOUT","SECURITY_BLOCKED","PARTIAL"}
    def evidence(self,result):
        return result.get("evidence")
