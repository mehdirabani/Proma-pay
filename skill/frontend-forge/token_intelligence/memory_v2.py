import hashlib,time
STATES={'TRANSIENT','VALIDATED','PROJECT_READY','STALE','CONTRADICTED','REVOKED'}
class MemoryEntry:
    def __init__(self,key,value,source_files,source_hashes,project_revision,graph_version,decision_dependencies=(),state='VALIDATED'):
        self.key=key;self.value=value;self.source_files=list(source_files);self.source_hashes=dict(source_hashes);self.project_revision=project_revision;self.graph_version=graph_version;self.decision_dependencies=list(decision_dependencies);self.state=state;self.created_at=time.time();self.validated_at=self.created_at
    def validate(self,current_hashes,project_revision,graph_version):
        if self.state in {'REVOKED','CONTRADICTED'}:return self.state
        if self.project_revision!=project_revision or self.graph_version!=graph_version:self.state='STALE';return self.state
        for p,h in self.source_hashes.items():
            if current_hashes.get(p)!=h:self.state='STALE';return self.state
        return self.state
