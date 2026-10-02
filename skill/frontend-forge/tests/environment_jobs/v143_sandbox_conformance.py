from security.sandbox_policy import detect_sandbox_backend
import json,sys
b=detect_sandbox_backend(require_full=True);print(json.dumps({"status":"PASS" if b else "BLOCKED","backend":b.name if b else None}));sys.exit(0 if b else 2)
