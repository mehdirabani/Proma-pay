RANK={'LOW':0,'MEDIUM':1,'HIGH':2,'CRITICAL':3}
def _maxrisk(*xs):
    vals=[x for x in xs if x in RANK];return max(vals,key=lambda x:RANK[x]) if vals else 'MEDIUM'
def choose_autonomy(task_model,target_safety,impact=None,approval_required=False,*,root_cause=None,code_intelligence_mode='REDUCED_REGEX',
                    test_coverage=None,tool_status=None,sandbox_status=None,validation_plan=None,evidence_quality=None):
    if approval_required:return {'level':'L2_PLAN','reason':'approval required','composite_risk':'HIGH'}
    state=target_safety.get('state')
    if state!='TARGET_CONFIRMED':return {'level':'L1_SUGGEST' if state!='TARGET_NOT_FOUND' else 'L0_ANALYZE_ONLY','reason':'target not confirmed','composite_risk':'HIGH'}
    impact=impact or {};centrality=float(impact.get('centrality',0));public=bool(impact.get('public_api_impact'))
    centrality_risk='HIGH' if centrality>=.65 else 'MEDIUM' if centrality>=.3 else 'LOW'
    validation_risk='HIGH' if validation_plan is not None and any(x.get('status') in {'UNVERIFIED','TOOL_UNAVAILABLE','TOOL_TIMEOUT','FAIL'} for x in validation_plan if isinstance(x,dict)) else 'LOW'
    evidence_risk='HIGH' if evidence_quality is not None and evidence_quality<.55 else 'MEDIUM' if evidence_quality is not None and evidence_quality<.7 else 'LOW'
    coverage_risk='HIGH' if test_coverage is not None and test_coverage<.35 else 'LOW'
    risk=_maxrisk(task_model.get('risk','MEDIUM'),impact.get('risk','LOW'),centrality_risk,'HIGH' if public else 'LOW',evidence_risk,validation_risk,coverage_risk)
    repair_semantic = task_model.get('operation')=='REPAIR' or (task_model.get('operation') is None and task_model.get('task_type') in {'BUG_FIX','RESPONSIVE_FIX','ACCESSIBILITY_FIX','PERFORMANCE_FIX','TYPE_ERROR','BUILD_FAILURE','TEST_FAILURE'})
    if repair_semantic and (root_cause or {}).get('status')!='ROOT_CAUSE_CONFIRMED':
        return {'level':'L2_PLAN','reason':'root cause unverified','composite_risk':risk}
    if code_intelligence_mode=='REDUCED_REGEX' and (risk in {'HIGH','CRITICAL'} or (task_model.get('operation') in {'REFACTOR','MIGRATE'} or task_model.get('task_type') in {'REFACTOR','ARCHITECTURE_CHANGE'})):
        return {'level':'L2_PLAN','reason':'reduced code intelligence for high-risk change','composite_risk':risk}
    if tool_status in {'TOOL_UNAVAILABLE','TOOL_TIMEOUT','UNVERIFIED','FAIL'}:return {'level':'L2_PLAN','reason':'required tool not verified','composite_risk':risk}
    if sandbox_status in {'SANDBOX_UNAVAILABLE','UNVERIFIED'} and risk in {'HIGH','CRITICAL'}:return {'level':'L2_PLAN','reason':'sandbox unavailable','composite_risk':risk}
    if public or risk in {'HIGH','CRITICAL'}:return {'level':'L2_PLAN','reason':'high impact/centrality/public API risk','composite_risk':risk}
    if risk=='MEDIUM':return {'level':'L3_IMPLEMENT_LOW_RISK','reason':'medium composite risk','composite_risk':risk}
    return {'level':'L4_IMPLEMENT_AND_VALIDATE','reason':'low composite risk with verified evidence','composite_risk':risk}
