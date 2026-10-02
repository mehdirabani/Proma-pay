import re,shutil
class CommandPolicyError(ValueError): pass
ALLOWLIST={"npm","pnpm","yarn","node","npx","git","tsc","eslint","prettier","lighthouse"}
BAD=re.compile(r"[;&|`$<>\n\r]")
def validate_command(argv):
    if not isinstance(argv,list) or not argv or not all(isinstance(x,str) for x in argv): raise CommandPolicyError("argv list required")
    exe=argv[0]
    if exe not in ALLOWLIST: raise CommandPolicyError(f"command not allowed: {exe}")
    for a in argv:
        if BAD.search(a): raise CommandPolicyError("shell metacharacter rejected")
        if "\x00" in a: raise CommandPolicyError("NUL rejected")
    if shutil.which(exe) is None: return False
    return True
