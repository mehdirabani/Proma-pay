from abc import ABC,abstractmethod
class AgentUsageAdapter(ABC):
    @abstractmethod
    def execute(self,task,context,mode):...
    @abstractmethod
    def usage(self,result):...
class UnavailableAgentUsageAdapter(AgentUsageAdapter):
    def execute(self,*a,**k):return {'status':'PROVIDER_USAGE_UNAVAILABLE','output':None,'artifacts':[]}
    def usage(self,result):return {'status':'PROVIDER_USAGE_UNAVAILABLE','input_tokens':None,'output_tokens':None,'cached_input_tokens':None,'reasoning_tokens':None}
