EVIDENCE_TYPES = {
    "REPOSITORY_SYMBOL", "DEPENDENCY_GRAPH", "ROUTE", "TEST", "UI_STRING",
    "STATIC_ANALYSIS", "BROWSER", "GIT", "TYPECHECK", "BUILD", "REPRODUCTION",
    "RUNTIME", "TASK_LINK", "INTERVENTION"
}
REQUIRED = {
    "evidence_id","record_type","evidence_type","claim","candidate","task_id","session_id",
    "workspace_fingerprint","project_revision","collector_id","collector_version",
    "collector_code_hash","source_artifact","source_artifact_hash","observation",
    "producer_id","source_id","derivation_parent_ids","created_at","execution_id",
    "key_id","signature"
}
