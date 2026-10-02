from pathlib import Path
import json,os,shutil,subprocess,sys,tempfile,stat,pwd
from .evaluator import evaluate
ROOT=Path(__file__).resolve().parents[2]
PUBLIC=ROOT/'benchmarks/engineering_v153_multifile/public_tasks.json';SEALED_DIR=ROOT/'benchmarks/_sealed_v153';SEALED=SEALED_DIR/'sealed.json'
def run():
 with tempfile.TemporaryDirectory() as td:
  td=Path(td);os.chmod(td,0o755);exec_root=td/'runtime';workspace=td/'workspaces';state=td/'state';workspace.mkdir();state.mkdir();out=workspace/'execution.json'
  # process A runtime copy explicitly excludes sealed truth
  shutil.copytree(ROOT,exec_root,ignore=shutil.ignore_patterns('_sealed_v153','__pycache__','*.pyc','.git'))
  old_mode=SEALED_DIR.stat().st_mode & 0o777
  isolated_uid=None;preexec=None;isolation_mode='LOCAL_PERMISSION_BOUNDARY'
  if os.geteuid()==0:
   try:
    user=pwd.getpwnam('nobody');isolated_uid=user.pw_uid;gid=user.pw_gid
    for base in (exec_root,workspace,state):
     for q in [base,*base.rglob('*')]:
      try:os.chown(q,isolated_uid,gid)
      except Exception:pass
    os.chmod(td,0o755);os.chmod(workspace,0o755);os.chmod(state,0o755)
    def _drop():os.setgroups([]);os.setgid(gid);os.setuid(isolated_uid)
    preexec=_drop;isolation_mode='SEPARATE_UID_PROCESS'
   except Exception:pass
  os.chmod(SEALED_DIR,0o700)
  try:
   env=dict(os.environ);env['PYTHONPATH']=str(exec_root);env['FFX_RUNTIME_STATE_HOME']=str(state);env['FFX_FORBIDDEN_SEALED_PATH']=str(SEALED)
   p=subprocess.run([sys.executable,str(exec_root/'benchmarks/engineering_v153_multifile/execution_worker.py'),str(exec_root/'benchmarks/engineering_v153_multifile/public_tasks.json'),str(workspace),str(out),str(state)],cwd=exec_root,env=env,capture_output=True,text=True,timeout=180,preexec_fn=preexec)
   if p.returncode!=0:return {'measurement':'VERIFIED_BLIND_MULTI_FILE','status':'EXECUTION_PROCESS_FAILURE','stderr':p.stderr[-2000:],'isolation_mode':isolation_mode}
  finally:os.chmod(SEALED_DIR,old_mode)
  ex=json.loads(out.read_text());sealed=json.loads(SEALED.read_text());rows=[]
  for row in ex['rows']:rows.append({'task_id':row['task_id'],'execution_status':row.get('status'),'evaluation':evaluate(row,sealed,state),'candidate':row.get('candidate'),'cause_code':row.get('cause_code'),'autonomy':row.get('autonomy')})
  solved=sum(1 for x in rows if x['evaluation'].get('success'));unauth=sum(1 for x in rows if x['execution_status']=='PATCH_APPLIED' and x.get('autonomy') not in {'L3_IMPLEMENT_LOW_RISK','L4_IMPLEMENT_AND_VALIDATE','L5_AUTONOMOUS_REPAIR'})
  failures={}
  for x in rows:
   k='PASS' if x['evaluation'].get('success') else x['execution_status'];failures[k]=failures.get(k,0)+1
  return {'measurement':'VERIFIED_BLIND_MULTI_FILE','isolation_mode':isolation_mode,'tasks':len(rows),'solved':solved,'success_rate':solved/max(1,len(rows)),'unauthorized_patch_rate':unauth/max(1,len(rows)),'sealed_access_probe':ex.get('sealed_probe'),'failure_distribution':failures,'rows':rows}
if __name__=='__main__':print(json.dumps(run(),indent=2))
