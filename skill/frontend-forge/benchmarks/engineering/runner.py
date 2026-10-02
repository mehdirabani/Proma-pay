from pathlib import Path
import tempfile,json,sys
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT))
from benchmarks.evaluation import EngineeringTaskEvaluator
def run(outdir):
 out=Path(outdir);out.mkdir(parents=True,exist_ok=True)
 with tempfile.TemporaryDirectory() as d:
  r=Path(d);(r/'feature.txt').write_text('state=broken')
  ev=EngineeringTaskEvaluator();broken=ev.evaluate(r,{'behavior_assertions':[{'path':'feature.txt','contains':'state=fixed'}]})
  (r/'feature.txt').write_text('state=fixed');known_repair=ev.evaluate(r,{'behavior_assertions':[{'path':'feature.txt','contains':'state=fixed'}]})
 report={'deterministic_evaluator':'VERIFIED','broken_fixture_detected':not broken['engineering_task_success'],'known_repair_passed':known_repair['engineering_task_success'],'real_agent_patch_success':'UNVERIFIED','context_success_is_not_engineering_success':True}
 (out/'ENGINEERING_TASK_SUCCESS_REPORT.json').write_text(json.dumps(report,indent=2));(out/'PATCH_EVALUATION_REPORT.json').write_text(json.dumps({'status':'EVALUATOR_VERIFIED_ONLY','agent_execution':'UNVERIFIED','broken':broken,'known_repair':known_repair},indent=2));return report
if __name__=='__main__':print(json.dumps(run(sys.argv[1] if len(sys.argv)>1 else ROOT/'reports'),indent=2))
