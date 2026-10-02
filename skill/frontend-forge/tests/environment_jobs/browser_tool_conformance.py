from browser.browser_runtime import BrowserRuntime
import tempfile,json,sys
with tempfile.TemporaryDirectory() as d:
    r=BrowserRuntime(d).availability()
    print(json.dumps(r))
    sys.exit(0 if r["status"]=="AVAILABLE" else 2)
