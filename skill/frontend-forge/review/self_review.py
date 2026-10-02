from .acceptance_ledger import create_ledger,apply_results,coverage
def _validation_disposition(status):
    if status=='PASS':return 'PASS'
    if status=='CANCELLED':return 'REVIEW_CANCELLED'
    if status in {'TOOL_TIMEOUT','SANDBOX_UNAVAILABLE','BLOCKED'}:return 'REVIEW_BLOCKED'
    if status in {'UNVERIFIED','TOOL_UNAVAILABLE','PARTIAL','UNIMPLEMENTED'}:return 'REVIEW_INCOMPLETE'
    return 'REVIEW_FAILED'
def self_review(task_model,root_cause,diff_report,validation,*,target_safety=None,acceptance_results=None,invariant_results=None,impact_validation=None):
    issues=[];dispositions=[_validation_disposition(x.get('status','UNKNOWN')) for x in (validation or [])]
    repair_semantic = task_model.get('operation')=='REPAIR' or (task_model.get('operation') is None and (not task_model.get('task_type') or task_model.get('task_type') in {'BUG_FIX','RESPONSIVE_FIX','ACCESSIBILITY_FIX','PERFORMANCE_FIX','TYPE_ERROR','BUILD_FAILURE','TEST_FAILURE'}))
    if repair_semantic and root_cause.get('status')!='ROOT_CAUSE_CONFIRMED':issues.append('root cause not confirmed')
    if target_safety is not None and target_safety.get('state')!='TARGET_CONFIRMED':issues.append('target not confirmed')
    if diff_report.get('unrelated_changes'):issues.append('unrelated changes')
    if diff_report.get('anti_pattern_flags'):issues.append('new anti-pattern flags')
    ledger=apply_results(create_ledger(task_model.get('acceptance_criteria',[])),acceptance_results)
    acc_cov=coverage(ledger)
    if ledger and acc_cov<1:issues.append('acceptance criteria incomplete')
    if invariant_results and any(not x.get('preserved',False) for x in invariant_results):issues.append('invariant violation')
    if impact_validation and impact_validation.get('status') not in {'PASS',None}:issues.append('impact validation incomplete')
    bad=[x for x in dispositions if x!='PASS']
    if 'REVIEW_CANCELLED' in bad:status='REVIEW_CANCELLED'
    elif 'REVIEW_BLOCKED' in bad:status='REVIEW_BLOCKED'
    elif 'REVIEW_FAILED' in bad or issues:status='REVIEW_FAILED'
    elif 'REVIEW_INCOMPLETE' in bad:status='REVIEW_INCOMPLETE'
    else:status='PASS'
    return {'status':status,'issues':issues,'validation_dispositions':dispositions,'acceptance_coverage':round(acc_cov,4),
            'acceptance_criteria_verified':sum(1 for x in ledger if x['status']=='PASS' and x['evidence']),
            'acceptance_criteria_total':len(ledger),'acceptance_ledger':ledger}
