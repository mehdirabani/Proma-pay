from abc import ABC,abstractmethod
class BenchmarkAgentHost(ABC):
    @abstractmethod
    def run(self,task,context):
        """Return {'output':..., 'usage': provider usage if exposed, 'artifacts':...}."""
        ...
def benchmark_status(host=None):return {'LLM_TOKEN_BENCHMARK':'UNVERIFIED'} if host is None else {'LLM_TOKEN_BENCHMARK':'AVAILABLE'}
