import shutil,json,sys
r={"status":"PASS" if shutil.which("lighthouse") else "TOOL_UNAVAILABLE"};print(json.dumps(r));sys.exit(0 if r["status"]=="PASS" else 2)
