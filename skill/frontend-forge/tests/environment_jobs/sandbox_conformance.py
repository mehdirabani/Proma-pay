from security.sandbox_policy import SandboxPolicy,detect_sandbox_backend
import json,sys
b=detect_sandbox_backend(require_full=True)
if not b:
    print(json.dumps({"status":"BLOCKED","reason":"full sandbox backend unavailable"}));sys.exit(2)
print(json.dumps({"status":"PASS","backend":b.name}))
