from .base_agent_host import AgentHost
class LocalAgentHost(AgentHost):
    def __init__(self,handlers):self.handlers=handlers
    def execute(self,agent,task,context):
        if agent not in self.handlers:raise KeyError(agent)
        return self.handlers[agent](task,context)
