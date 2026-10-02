from browser.browser_runtime import BrowserRuntime
from pathlib import Path
import json,sys
r=BrowserRuntime(Path(__file__).resolve().parents[2]).availability();print(json.dumps(r));sys.exit(0 if r.get("status")=="AVAILABLE" else 2)
