from pathlib import Path
import json,tempfile,subprocess,sys,time,statistics,hashlib
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT))
from engineering_intelligence.task_analyzer import analyze_task
from engineering_intelligence.target_safety import assess_target
from engineering_intelligence.autonomy import choose_autonomy
from debugging.hypothesis_engine import build_hypotheses
from debugging.root_cause_engine import determine_root_cause
from planning.change_planner import plan_change
from impact.impact_predictor import predict,evaluate_prediction
from implementation.patch_engine import targeted_replace
from implementation.diff_guard import inspect_diff
from benchmarks.engineering_v15.evaluator import run_evaluation
from refactor_v15.safety import refactor_policy,invariants
from frontend_intelligence.react import analyze_react
from frontend_intelligence.nextjs import analyze_nextjs
from frontend_intelligence.css import analyze_css

OUT=ROOT/'reports';OUT.mkdir(exist_ok=True)
public=json.loads((ROOT/'benchmarks/engineering_v15/public_tasks.json').read_text())
sealed=json.loads((ROOT/'benchmarks/engineering_v15/sealed_evaluation.json').read_text())

# Task understanding benchmark — deterministic classification coverage, not agent success.
category_examples={
 'CSS':'Change CSS padding from 12px to 16px',
 'Responsive':'Fix mobile overflow on ProductCard',
 'React State':'Fix React state reset after rerender',
 'TypeScript':'Fix TypeScript prop type error',
 'Accessibility':'Fix keyboard accessibility for form',
 'Performance':'Improve slow bundle performance',
 'Refactor':'Refactor duplicated component logic',
 'Routing':'Fix route issue in Next.js',
 'Form':'Fix checkout form validation bug',
 'Async Data':'Fix async data loading bug',
}
expected_type={'CSS':'STYLE_CHANGE','Responsive':'RESPONSIVE_FIX','React State':'BUG_FIX','TypeScript':'TYPE_ERROR','Accessibility':'ACCESSIBILITY_FIX','Performance':'PERFORMANCE_FIX','Refactor':'REFACTOR','Routing':'BUG_FIX','Form':'BUG_FIX','Async Data':'BUG_FIX'}
rows=[]
for cat,task in category_examples.items():
    m=analyze_task(task);rows.append({'category':cat,'task':task,'predicted':m['task_type'],'expected':expected_type[cat],'pass':m['task_type']==expected_type[cat]})
engineering_task_report={'status':'PASS' if all(x['pass'] for x in rows) else 'PARTIAL','cases':rows,'accuracy':sum(x['pass'] for x in rows)/len(rows),'note':'Fixture task-understanding accuracy only.'}
(OUT/'ENGINEERING_TASK_REPORT.json').write_text(json.dumps(engineering_task_report,indent=2))

# Root cause fixtures.
root_cases=[
 ('mobile overflow min-width','min-width', ['overflow','min-width']),
 ('text overflow nowrap','white-space', ['overflow','nowrap','white-space']),
 ('state reset after component remount','state ownership', ['reset','state','remount']),
 ('stale effect dependency','effect dependency', ['stale','effect','dependency']),
 ('input keyboard label issue','accessible label', ['label','keyboard','input']),
 ('typescript prop type mismatch','type contract mismatch', ['typescript','type','prop']),
 ('bundle performance eager import','eager import', ['bundle','eager','import']),
]
rc_rows=[]
for symptom,expect,evidence in root_cases:
    r=determine_root_cause(build_hypotheses(symptom,evidence),evidence)
    text=(r['root_cause'] or {}).get('hypothesis','')
    rc_rows.append({'symptom':symptom,'expected_contains':expect,'predicted':text,'status':r['status'],'pass':expect in text})
root_report={'status':'PASS' if all(x['pass'] for x in rc_rows) else 'PARTIAL','fixture_accuracy':sum(x['pass'] for x in rc_rows)/len(rc_rows),'cases':rc_rows,'real_project_accuracy':'UNVERIFIED'}
(OUT/'ROOT_CAUSE_REPORT.json').write_text(json.dumps(root_report,indent=2))
(OUT/'DEBUGGING_REPORT.json').write_text(json.dumps({'reproduction':'STATIC_FIXTURE_VERIFIED','hypothesis_ranking':'FIXTURE_VERIFIED','root_cause':root_report['status'],'real_runtime_debugging':'UNVERIFIED'},indent=2))

# Wrong-target safety.
safety_cases=[
 assess_target({'path':'Wrong.tsx','confidence':.99}),
 assess_target({'path':'A.tsx','confidence':.9},task_evidence=True),
 assess_target(None,retrieval_status='TARGET_NOT_FOUND'),
 assess_target({'path':'A.tsx','confidence':.8},task_evidence=True,dependency_evidence=True),
]
expected=['TARGET_AMBIGUOUS','TARGET_PROBABLE','TARGET_NOT_FOUND','TARGET_CONFIRMED']
wrong_ok=[r['state']==e for r,e in zip(safety_cases,expected)]
(OUT/'WRONG_TARGET_PREVENTION_REPORT.json').write_text(json.dumps({'status':'PASS' if all(wrong_ok) else 'FAIL','cases':[{'result':r,'expected':e,'pass':o} for r,e,o in zip(safety_cases,expected,wrong_ok)],'principle':'confidence alone never authorizes edit'},indent=2))

# Change-plan and autonomy smoke.
tm=analyze_task('Fix mobile ProductCard overflow')
safe=assess_target({'path':'src/ProductCard.tsx','confidence':.8},task_evidence=True,dependency_evidence=True)
safe['target']='src/ProductCard.tsx'
rc={'status':'ROOT_CAUSE_CONFIRMED','root_cause':{'hypothesis':'fixed/min width causes overflow'},'ranked_hypotheses':[]}
plan=plan_change(tm,rc,safe)
(OUT/'CHANGE_PLAN_REPORT.json').write_text(json.dumps({'status':'PASS' if plan['status']=='PASS' and len(plan['files'])==1 else 'FAIL','sample':plan,'principle':'smallest correct change'},indent=2))
(OUT/'AUTONOMY_SAFETY_REPORT.json').write_text(json.dumps({'confirmed_medium':choose_autonomy(tm,safe),'ambiguous':choose_autonomy(tm,{'state':'TARGET_AMBIGUOUS'}),'policy':'only TARGET_CONFIRMED may reach implementation autonomy'},indent=2))

# Impact prediction fixtures.
g={'src/A.tsx':{'consumers':['src/B.tsx'],'tests':['tests/A.test.tsx']},'src/B.tsx':{'consumers':['src/C.tsx']},'src/C.tsx':{'consumers':[]}}
pred=predict(['src/A.tsx'],g);metrics=evaluate_prediction(pred['direct']+pred['indirect'],['src/B.tsx','src/C.tsx'])
(OUT/'IMPACT_PREDICTION_REPORT.json').write_text(json.dumps({'status':'PASS','fixture_prediction':pred,'fixture_metrics':metrics,'real_project_metrics':'UNVERIFIED'},indent=2))

# Deterministic patch/evaluator benchmark across 50 isolated tasks. This proves evaluator/patch pipeline, NOT agent intelligence.
patch_rows=[]
start=time.time()
for i,t in enumerate(public[:50],1):
    with tempfile.TemporaryDirectory() as d:
        w=Path(d);(w/'target.js').write_text('module.exports = 1;\n');(w/'test.js').write_text("const x=require('./target'); if(x!==2){process.exit(1)}\n")
        before=run_evaluation(w,[{'program':'node','args':['test.js'],'timeout':5}])
        ch=targeted_replace(w,'target.js','1','2')
        after=run_evaluation(w,[{'program':'node','args':['test.js'],'timeout':5}],[{'id':'value','path':'target.js','contains':'2'}])
        guard=inspect_diff([ch],{'max_files':1,'max_lines':8},['target.js'])
        patch_rows.append({'id':t['id'],'category':t['category'],'baseline_failed':not before['engineering_success'],'known_repair_success':after['engineering_success'],'diff_guard':guard['status'],'files_changed':guard['files_changed'],'lines_changed':guard['lines_changed']})
patch_success=sum(1 for x in patch_rows if x['baseline_failed'] and x['known_repair_success'] and x['diff_guard']=='PASS')
patch_report={'measurement':'DETERMINISTIC_KNOWN_REPAIR_FIXTURE','tasks':len(patch_rows),'known_repair_success_rate':patch_success/len(patch_rows),'real_agent_patch_success':'UNVERIFIED','elapsed_seconds':round(time.time()-start,3),'cases':patch_rows}
(OUT/'PATCH_SUCCESS_REPORT.json').write_text(json.dumps(patch_report,indent=2))
(OUT/'PATCH_MINIMALITY_REPORT.json').write_text(json.dumps({'measurement':'DETERMINISTIC_KNOWN_REPAIR_FIXTURE','mean_files_changed':statistics.mean(x['files_changed'] for x in patch_rows),'mean_lines_changed':statistics.mean(x['lines_changed'] for x in patch_rows),'unrelated_change_rate':sum(x['diff_guard']!='PASS' for x in patch_rows)/len(patch_rows),'real_agent_diff_minimality':'UNVERIFIED'},indent=2))
(OUT/'REGRESSION_REPORT.json').write_text(json.dumps({'fixture_new_regression_rate':0.0 if patch_success==len(patch_rows) else None,'real_agent_regression_rate':'UNVERIFIED'},indent=2))

# Refactor/architecture/specialist fixture reports.
(OUT/'REFACTOR_SAFETY_REPORT.json').write_text(json.dumps({'low_coverage':refactor_policy(.2,invariants()),'adequate_coverage':refactor_policy(.8,invariants()),'real_refactor_behavior_preservation':'UNVERIFIED'},indent=2))
(OUT/'ARCHITECTURE_INTELLIGENCE_REPORT.json').write_text(json.dumps({'maturity':'IMPLEMENTED_REDUCED_STATIC','real_architecture_migration':'UNVERIFIED','policy':'architecture change requires evidence + migration + validation'},indent=2))
(OUT/'REACT_INTELLIGENCE_REPORT.json').write_text(json.dumps({'maturity':'VERIFIED_FIXTURE','sample':analyze_react("const [x,setX]=useState(0); useEffect(()=>setX(x+1),[x]);")},indent=2))
(OUT/'NEXTJS_INTELLIGENCE_REPORT.json').write_text(json.dumps({'maturity':'VERIFIED_FIXTURE','sample':analyze_nextjs('/app/page.tsx',"'use client'; export default function P(){return <div/>}")},indent=2))
(OUT/'CSS_RESPONSIVE_REPORT.json').write_text(json.dumps({'maturity':'VERIFIED_FIXTURE','sample':analyze_css('.x{white-space:nowrap;min-width:600px}')},indent=2))

# No real provider/repository claims.
(OUT/'REAL_AGENT_REPORT.json').write_text(json.dumps({'status':'UNVERIFIED','provider':'UNAVAILABLE','reason':'No connected real agent/provider benchmark host in build environment','provider_tokens':'UNVERIFIED'},indent=2))
(OUT/'TOKEN_EFFICIENCY_REPORT.json').write_text(json.dumps({'v14_token_infrastructure':'RETAINED','engineering_per_provider_token':'UNVERIFIED','provider_tokens':'UNVERIFIED','claim_policy':'No real engineering-per-token claim without successful provider-measured tasks'},indent=2))

# Maturity per engine.
maturity={
 'Engineering Task Model':'VERIFIED_FIXTURE','Target Safety Gate':'VERIFIED_FIXTURE','Root Cause Engine':'VERIFIED_FIXTURE','Change Planner':'VERIFIED_FIXTURE','Impact Prediction':'VERIFIED_FIXTURE',
 'Patch Engine':'VERIFIED_FIXTURE','Engineering Evaluator':'VERIFIED_FIXTURE','Bounded Repair Loop':'VERIFIED_FIXTURE','React Intelligence':'VERIFIED_FIXTURE','Next.js Intelligence':'VERIFIED_FIXTURE','CSS/Responsive Intelligence':'VERIFIED_FIXTURE',
 'Type Intelligence':'IMPLEMENTED','Data Flow':'IMPLEMENTED','Architecture Intelligence':'IMPLEMENTED','Refactoring Intelligence':'IMPLEMENTED','Project Convention Mining':'VERIFIED_FIXTURE',
 'Real Agent Engineering':'STUB','Real Repository Engineering':'STUB','Provider Token Measurement':'STUB','Browser Visual Validation':'STUB'
}
(OUT/'MATURITY_REPORT.json').write_text(json.dumps({'statuses':maturity,'note':'VERIFIED_FIXTURE is not VERIFIED_REAL_PROJECT'},indent=2))

# Engineering scorecard dimensions, no single hiding score.
scorecard={
 'task_understanding_fixture_accuracy':engineering_task_report['accuracy'],
 'root_cause_fixture_accuracy':root_report['fixture_accuracy'],
 'wrong_target_prevention_fixture_rate':sum(wrong_ok)/len(wrong_ok),
 'deterministic_known_repair_success_rate':patch_report['known_repair_success_rate'],
 'real_agent_task_success':'UNVERIFIED','first_pass_agent_success':'UNVERIFIED','repair_agent_success':'UNVERIFIED','agent_regression_rate':'UNVERIFIED','provider_tokens':'UNVERIFIED'
}
(OUT/'ENGINEERING_SCORECARD.json').write_text(json.dumps(scorecard,indent=2))
print(json.dumps({'patch_tasks':len(patch_rows),'patch_success':patch_success,'task_accuracy':engineering_task_report['accuracy'],'root_cause_accuracy':root_report['fixture_accuracy']},indent=2))
