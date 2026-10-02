from pathlib import Path
import re, json

def repository_symbol_observation(workspace,candidate,index=None):
    node=(index or {}).get("nodes",{}).get(candidate,{})
    syms=list(node.get("symbols") or node.get("exports") or [])
    return {
        "claim_type":"SYMBOL_DEFINITION",
        "observation":"candidate defines symbols: "+",".join(syms[:12]) if syms else "candidate has no indexed symbols",
        "source_artifact":candidate,
        "source_id":"repository-index:symbols",
        "directness":0.65,
        "metadata":{"symbols":syms[:12]}
    }

def dependency_observation(workspace,candidate,index=None):
    node=(index or {}).get("nodes",{}).get(candidate,{})
    rel={}
    for k in ("imports","reverse_imports","consumers","routes","tests","styles","public_consumers"):
        if node.get(k):rel[k]=list(node[k])[:20]
    return {
        "claim_type":"DEPENDENCY_RELATIONSHIP",
        "observation":"repository graph relationships: "+",".join(sorted(rel)) if rel else "no graph relationships",
        "source_artifact":candidate,
        "source_id":"repository-index:graph",
        "directness":0.75,
        "metadata":{"relationships":rel}
    }

def static_task_link_observation(workspace,candidate,task,index=None):
    p=(Path(workspace)/candidate).resolve()
    text=p.read_text(errors="ignore") if p.exists() else ""
    toks=set(re.findall(r"[a-z0-9\u0600-\u06ff]+",task.lower()))
    path_words=set(re.findall(r"[a-z0-9\u0600-\u06ff]+",str(candidate).lower()))
    words=set(re.findall(r"[a-z0-9\u0600-\u06ff]+",text.lower())) | path_words
    stop={"fix","add","make","the","this","that","with","from","برای","این","را","کن","شود","در"}
    overlap=sorted(x for x in toks&words if len(x)>2 and x not in stop)
    return {
        "claim_type":"TASK_TARGET_LINK",
        "observation":"task terms found in candidate: "+",".join(overlap[:15]),
        "source_artifact":candidate,
        "source_id":"repository-static:task-link",
        "directness":0.9 if overlap else 0.1,
        "metadata":{"overlap":overlap[:15]}
    }

def root_cause_static_observations(workspace,candidate,task,index=None):
    p=(Path(workspace)/candidate).resolve()
    if not p.exists():return []
    text=p.read_text(errors="ignore")
    patterns=[
      ("CSS_MIN_WIDTH",r"min-width\s*:\s*\d+px","fixed minimum width observed","responsive"),
      ("NOWRAP",r"white-space\s*:\s*nowrap","nowrap constraint observed","responsive"),
      ("MISSING_LABEL",r"<input(?![^>]*(?:aria-label|aria-labelledby))","input without accessible label evidence","accessibility"),
      ("EAGER_HEAVY_IMPORT",r"import\s+\w*Heavy\w*\s+from","eager heavy import observed","performance"),
      ("ROUTE_TYPO",r"href=['\"]/(?:profil|chekout|dashbord)['\"]","route path mismatch observed","routing"),
      ("NON_SUBMIT",r"type=['\"]button['\"][^>]*>\s*(?:Submit|Pay|ارسال)","submit control configured as button","form"),
      ("LOADING_NULL",r"if\s*\(\s*loading\s*\)\s*return\s+null","loading state removes feedback","async-data"),
      ("CLIENT_BOUNDARY",r"['\"]use client['\"];?","client boundary observed","nextjs"),
      ("ARBITRARY_TW",r"(?:p|m|w|h)-\[\d+px\]","arbitrary Tailwind pixel utility observed","css"),
      ("TYPE_STRING",r"\b\w+\s*:\s*string\b","string type contract observed","typescript"),
      ("DUPLICATE_CALC",r"const\s+\w+\s*=\s*\w+\*2;\s*const\s+\w+\s*=\s*\w+\*2","duplicate calculation observed","refactor"),
      ("COLOR",r"(?:color|bg-color|background(?:-color)?)\s*:\s*[^;]+","color declaration observed","css"),
    ]
    out=[]
    for claim,pat,obs,domain in patterns:
        m=re.search(pat,text,re.I|re.M|re.S)
        if m:
            out.append({
              "claim_type":"ROOT_CAUSE_OBSERVATION",
              "observation":obs+" :: "+m.group(0)[:120],
              "source_artifact":candidate,"source_id":"repository-static:root-cause",
              "directness":0.9,"metadata":{"pattern_id":claim,"domain":domain,"match":m.group(0)[:120]}
            })
    return out
