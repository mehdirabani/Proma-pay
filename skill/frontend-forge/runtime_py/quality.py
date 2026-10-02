from .io_utils import load_json

def _normalize(metric, raw):
    contracts = load_json("quality/measurement-contracts.json")
    contract = contracts[metric]
    adapter = contract["adapter"]

    if isinstance(raw, (int, float)):
        value = float(raw)
        source = "normalized"
        evidence = {"value": raw}
        baseline = None
    else:
        source = raw.get("source","unknown")
        evidence = raw.get("evidence", raw)
        baseline = raw.get("baseline")
        if adapter == "lighthouse-or-normalized" and "lighthouse" in raw:
            category = "performance" if metric == "performance" else "seo"
            value = float(raw["lighthouse"]["categories"][category]["score"]) * 100
        else:
            value = float(raw["value"])

    if not 0 <= value <= 100:
        raise ValueError(f"{metric} score out of range: {value}")
    return {"metric":metric,"value":round(value,2),"source":source,"evidence":evidence,"baseline":baseline}

def evaluate(profile_name, evidence):
    profiles = load_json("quality/project-profiles.json")
    if profile_name not in profiles:
        raise KeyError(f"unknown project profile: {profile_name}")
    p = profiles[profile_name]

    missing = [m for m in p["required_metrics"] if m not in evidence]
    measurements = {}
    for metric, raw in evidence.items():
        if metric in p["weights"]:
            measurements[metric] = _normalize(metric, raw)

    blocking_failures = []
    for metric, threshold in p["blocking"].items():
        if metric not in measurements:
            blocking_failures.append({"metric":metric,"reason":"missing evidence","threshold":threshold})
        elif measurements[metric]["value"] < threshold:
            blocking_failures.append({
                "metric":metric,
                "value":measurements[metric]["value"],
                "threshold":threshold,
                "reason":"below blocking threshold"
            })

    measured_weight = sum(p["weights"][m] for m in measurements)
    weighted = None
    if measured_weight:
        weighted = sum(measurements[m]["value"] * p["weights"][m] for m in measurements) / measured_weight
        weighted = round(weighted, 2)

    status = "PASS"
    if missing:
        status = "INCOMPLETE"
    if blocking_failures:
        status = "BLOCKED"
    elif weighted is not None and weighted < p["minimum_overall"]:
        status = "FAIL"

    return {
        "profile":profile_name,
        "status":status,
        "weighted_score":weighted,
        "minimum_overall":p["minimum_overall"],
        "missing_metrics":missing,
        "blocking_failures":blocking_failures,
        "measurements":measurements
    }
