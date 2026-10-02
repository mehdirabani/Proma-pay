from abc import ABC,abstractmethod
class RealAgentBenchmarkHost(ABC):
    @abstractmethod
    def run(self,task,context,mode):
        """Return output, usage fields reported by provider only, artifacts, latency."""
        ...
class UnavailableAgentHost(RealAgentBenchmarkHost):
    def run(self,task,context,mode):return {'status':'UNVERIFIED','output':None,'usage':{'input_tokens':None,'output_tokens':None,'cached_input_tokens':None},'artifacts':[]}
