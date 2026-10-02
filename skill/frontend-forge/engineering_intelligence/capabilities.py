from .task_analyzer import analyze_task
from .target_safety import assess_target
from .orchestrator import analyze_engineering_task
from debugging.hypothesis_engine import build_hypotheses
from debugging.root_cause_engine import determine_root_cause
from planning.change_planner import plan_change
from impact.impact_predictor import predict

def task_cap(ctx):return {'engineering_task':analyze_task(ctx['task'])}
def safety_cap(ctx):return {'target_safety':assess_target(ctx.get('candidate'),target_evidence=ctx.get('target_evidence'),task_evidence=ctx.get('task_evidence'),dependency_evidence=ctx.get('dependency_evidence'),repository_evidence=ctx.get('repository_evidence'),retrieval_status=ctx.get('retrieval_status'),revision=ctx.get('revision','UNKNOWN'),workspace=ctx.get('workspace',''),task_id=ctx.get('task_id','task'),mode=ctx.get('mode','PRODUCTION'))}
def root_cause_cap(ctx):return {'root_cause_report':determine_root_cause(build_hypotheses(ctx['symptom'],ctx.get('signals',[]),ctx.get('evidence',[])),ctx.get('evidence',[]),ctx.get('reproduction'))}
def plan_cap(ctx):return {'change_plan':plan_change(ctx['engineering_task'],ctx['root_cause_report'],ctx['target_safety'],ctx.get('impact'))}
def impact_cap(ctx):return {'impact_prediction':predict(ctx['change_files'],ctx['graph'])}
def orchestrator_cap(ctx):return {'engineering_runtime':analyze_engineering_task(ctx['task'],ctx['workspace'],candidate=ctx.get('candidate'),retrieval_status=ctx.get('retrieval_status'),repository_index=ctx.get('repository_index'),revision=ctx.get('revision','UNKNOWN'),task_id=ctx.get('task_id','task'),mode=ctx.get('mode','PRODUCTION'),reproduction_checks=ctx.get('reproduction_checks'))}
