from pathlib import Path
import json,re,subprocess

def requirement_reasoner(ctx):
    task=str(ctx["task"]);low=task.lower()
    project=ctx.get("project_type") or next((x for x in ["ecommerce","dashboard","landing","saas","portfolio","content-platform","web-application","design-system"] if x in low),"web-application")
    stack=ctx.get("stack") or next((x for x in ["nextjs","react","typescript","tailwind","javascript"] if x in low),"unknown")
    explicit=[x.strip() for x in re.split(r"[,;\n]",task) if x.strip()]
    risks=[x for x in ["payment","auth","migration","delete","security"] if x in low]
    caps=["frontend"]
    if "seo" in low:caps.append("seo")
    if "accessib" in low or "wcag" in low:caps.append("accessibility")
    if "performance" in low:caps.append("performance")
    return {"requirement_summary":{"status":"PASS","task_type":"frontend-engineering","project_type":project,"stack":stack,
        "explicit_requirements":explicit,"implicit_constraints":["preserve existing behavior"],"must_have":explicit,
        "optional":[],"unknowns":[],"risk_indicators":risks,"required_capabilities":sorted(set(caps)),"confidence":0.78 if stack=="unknown" else 0.9}}

def context_engine(ctx):
    root=Path(ctx["workspace"]).resolve()
    pkg={}
    p=root/"package.json"
    if p.exists():
        try:pkg=json.loads(p.read_text())
        except Exception:pkg={}
    deps={**pkg.get("dependencies",{}),**pkg.get("devDependencies",{})}
    framework="nextjs" if "next" in deps else "react" if "react" in deps else "unknown"
    styling="tailwind" if any(k.startswith("tailwind") for k in deps) else "css/unknown"
    tests="vitest" if "vitest" in deps else "jest" if "jest" in deps else "unknown"
    revision="UNKNOWN"
    try:revision=subprocess.run(["git","rev-parse","HEAD"],cwd=root,capture_output=True,text=True,timeout=3).stdout.strip() or "UNKNOWN"
    except Exception:pass
    tree=[str(x.relative_to(root)) for x in list(root.rglob("*"))[:500] if x.is_file() and ".git" not in x.parts]
    return {"context_summary":{"status":"PASS","workspace":str(root),"package_manager":"npm" if (root/"package-lock.json").exists() else "unknown",
        "framework":framework,"dependencies":sorted(deps),"scripts":pkg.get("scripts",{}),"source_tree":tree,
        "routing_system":"app-router" if (root/"app").exists() else "unknown","styling_system":styling,"test_system":tests,
        "build_system":"next" if framework=="nextjs" else pkg.get("scripts",{}).get("build","unknown"),"git_revision":revision}}

def impact_analyzer(ctx):
    root=Path(ctx.get("workspace",".")).resolve();changed=[str(x) for x in ctx.get("affected_files",[])]
    reverse={};direct={};tests=[];styles=[];routes=[]
    for rel in changed:
        p=(root/rel)
        if not p.exists():continue
        text=p.read_text(errors="ignore")
        imports=re.findall(r"(?:from\s+|require\()['\"]([^'\"]+)",text)
        direct[rel]=imports
        if any(k in rel.lower() for k in ["test","spec"]):tests.append(rel)
        if p.suffix in {".css",".scss",".sass"}:styles.append(rel)
        if any(x in rel for x in ["/app/","/pages/"]):routes.append(rel)
    risk="HIGH" if len(changed)>20 else "MEDIUM" if len(changed)>5 else "LOW"
    return {"impact_report":{"status":"PASS","mode":"REDUCED" if not direct else "STATIC_IMPORT_SCAN","direct_dependencies":direct,
        "reverse_dependencies":reverse,"component_consumers":[],"routes_affected":routes,"tests_affected":tests,
        "styles_affected":styles,"build_impact":"possible" if changed else "none","risk":risk}}

def dependency_resolver(ctx):return {"dependency_report":{"status":"PASS","resolved":True,"capabilities":ctx.get("capability_plan",[])}}

def planner(ctx):
    req=ctx["requirement_summary"];impact=ctx["impact_report"];caps=req.get("required_capabilities",["frontend"])
    steps=[{"id":"analyze","depends_on":[],"capabilities":["context-engine"]},
           {"id":"implement","depends_on":["analyze"],"capabilities":caps},
           {"id":"validate","depends_on":["implement"],"capabilities":["quality-engine","regression-runner"]}]
    return {"architecture_plan":{"status":"PASS","steps":steps,"dependencies":{"validate":["implement"]},"capabilities":caps,
        "files":list(impact.get("direct_dependencies",{})),"risk":impact.get("risk","MEDIUM"),"checkpoints":["before_implement","before_validation"],
        "validation_plan":["quality gates","regression"],"rollback_plan":"restore durable checkpoint"}}

def artifact_registration(ctx):return {"artifact_record":ctx["artifact"]}

def learning_record(ctx):
    outcome=ctx["outcome"];evidence=ctx.get("evidence",[])
    return {"learning_record":{"status":"candidate","candidate":outcome,"observations":1,"evidence":evidence,
        "confidence":0.0 if not evidence else 0.5,"validation":"PENDING","accepted":False}}

def observability_record(ctx):
    trace=ctx.get("trace",[]);durations=[x.get("duration_ms",0) for x in trace if isinstance(x,dict)]
    return {"audit_record":{"status":"PASS","event_count":len(trace),"execution_duration_ms":sum(durations),
        "capability_duration_ms":durations,"retry_count":sum(1 for x in trace if x.get("event")=="Retry"),
        "cancel_count":sum(1 for x in trace if "Cancel" in str(x.get("event"))),
        "rollback_count":sum(1 for x in trace if "Rollback" in str(x.get("event"))),
        "evidence_count":ctx.get("evidence_count",0),"sandbox_backend":ctx.get("sandbox_backend"),
        "failure_distribution":ctx.get("failure_distribution",{})}}

def agent_coordinator(ctx):return {"handoff_plan":{"status":"PASS","capabilities":ctx.get("capability_plan",[])}}
