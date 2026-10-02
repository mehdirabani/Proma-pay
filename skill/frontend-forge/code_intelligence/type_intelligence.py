import re,shutil,subprocess,json
from pathlib import Path
BRIDGE=Path(__file__).with_name('ts_compiler_bridge.js')
def compiler_available():
    if not shutil.which('node'):return False
    try:return subprocess.run(['node','-e','require("typescript");'],capture_output=True,timeout=4).returncode==0
    except Exception:return False
def analyze_file(path):
    p=Path(path).resolve()
    if compiler_available():
        try:
            r=subprocess.run(['node',str(BRIDGE),str(p)],capture_output=True,text=True,timeout=15,shell=False)
            data=json.loads(r.stdout.strip() or '{}')
            if data.get('status')=='PASS':return data
        except Exception:pass
    return {**analyze_types(p.read_text(errors='ignore') if p.exists() else ''),'mode':'REDUCED_REGEX','status':'PARTIAL'}
def analyze_types(text):
    interfaces=re.findall(r'interface\s+(\w+)',text);types=re.findall(r'type\s+(\w+)\s*=',text);generics=re.findall(r'<([A-Z](?:\s*,\s*[A-Z])*)>',text);unions=re.findall(r'\b([A-Za-z0-9_"\']+\s*\|\s*[A-Za-z0-9_"\'|\s]+)',text)
    return {'status':'PASS','mode':'REDUCED_REGEX','interfaces':interfaces,'types':types,'generics':generics,'unions':unions}
