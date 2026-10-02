from pathlib import Path
from engineering_evidence.broker import EngineeringEvidenceBroker
from engineering_evidence import collectors
class EvidenceCollector:
    def __init__(self,workspace,revision,task_id,repository_index=None,session_id='session',mode='SIMULATION'):
        self.root=Path(workspace).resolve();self.revision=revision;self.task_id=task_id;self.index=repository_index or {};self.mode=mode
        self.broker=EngineeringEvidenceBroker(self.root,revision,task_id,session_id=session_id) if mode=='PRODUCTION' else None
    def close(self):
        if self.broker:self.broker.close()
    def _issue(self,collector_id,raw,task,candidate):
        if self.mode=='PRODUCTION':return self.broker.issue(collector_id,raw,task,candidate)
        from engineering_intelligence.target_evidence import create_target_evidence
        kind={'TASK_TARGET_LINK':'STATIC_ANALYSIS','DEPENDENCY_RELATIONSHIP':'DEPENDENCY_GRAPH','SYMBOL_DEFINITION':'REPOSITORY_SYMBOL','ROOT_CAUSE_OBSERVATION':'STATIC_ANALYSIS'}.get(raw.get('claim_type'),'STATIC_ANALYSIS')
        return create_target_evidence(kind,str(candidate),collector_id,raw.get('claim_type','observation'),self.revision,str(self.root),self.task_id,raw.get('observation',''),metadata=raw.get('metadata',{}))
    def collect_target(self,task,candidate):
        rel=candidate.get('path') if isinstance(candidate,dict) else str(candidate);out=[]
        raw=collectors.static_task_link_observation(self.root,rel,task,self.index)
        if raw.get('metadata',{}).get('overlap'):out.append(self._issue('static-task-link-collector',raw,task,rel))
        raw=collectors.dependency_observation(self.root,rel,self.index)
        if raw.get('metadata',{}).get('relationships'):out.append(self._issue('dependency-graph-collector',raw,task,rel))
        raw=collectors.repository_symbol_observation(self.root,rel,self.index)
        if raw.get('metadata',{}).get('symbols'):out.append(self._issue('repository-symbol-collector',raw,task,rel))
        return out
    def collect_root_cause(self,task,candidate):
        rel=candidate.get('path') if isinstance(candidate,dict) else str(candidate);out=[]
        for raw in collectors.root_cause_static_observations(self.root,rel,task,self.index):
            if self.mode=='PRODUCTION':out.append(self._issue('static-analysis-collector',raw,task,rel))
            else:
                from debugging.evidence import create_evidence
                out.append(create_evidence('STATIC_ANALYSIS','repository-static',rel,raw.get('observation',''),rel,self.revision,.95,workspace=str(self.root),task_id=self.task_id,direct=True,metadata=raw.get('metadata',{})))
        return out
