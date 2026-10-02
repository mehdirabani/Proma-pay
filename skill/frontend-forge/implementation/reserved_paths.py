RESERVED_PREFIXES=(
 ".ffx-transactions",".frontend-forge-x",".ffx-state","signer-keys","engineering_evidence/",
 "benchmarks/engineering_v152_blind/sealed","provenance/state","runtime/state"
)
def validate_patch_path(path):
    x=str(path).replace("\\","/")
    while x.startswith("./"): x=x[2:]
    if any(x==p or x.startswith(p.rstrip("/")+"/") for p in RESERVED_PREFIXES):
        raise PermissionError("RESERVED_RUNTIME_PATH")
    return x
