DEFAULT_MAX_DROP = {
    "architecture":3,
    "code_quality":3,
    "ux":4,
    "performance":3,
    "accessibility":1,
    "seo":3,
    "security":0,
    "maintainability":3
}

def compare(baseline, current, max_drop=None):
    limits = dict(DEFAULT_MAX_DROP)
    if max_drop:
        limits.update(max_drop)
    regressions = []
    deltas = {}
    for metric, base in baseline.items():
        if metric not in current:
            regressions.append({"metric":metric,"reason":"missing current measurement"})
            continue
        delta = float(current[metric]) - float(base)
        deltas[metric] = round(delta, 2)
        if delta < -limits.get(metric, 3):
            regressions.append({
                "metric":metric,
                "baseline":base,
                "current":current[metric],
                "delta":round(delta,2),
                "max_allowed_drop":limits.get(metric,3)
            })
    return {
        "status":"REGRESSION" if regressions else "PASS",
        "deltas":deltas,
        "regressions":regressions
    }
