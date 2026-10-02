from abc import ABC,abstractmethod
class AgentHost(ABC):
    @abstractmethod
    def execute(self,agent,task,context): ...
