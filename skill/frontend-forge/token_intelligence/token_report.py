def saving_percentage(baseline,optimized):
    if baseline<=0:return None
    return round((1-optimized/baseline)*100,2)
def qate(baseline_tokens,optimized_tokens,baseline_quality,optimized_quality):
    if baseline_tokens<=0 or baseline_quality<=0:return None
    efficiency=(baseline_tokens/max(1,optimized_tokens))
    quality_ratio=optimized_quality/baseline_quality
    return round(efficiency*quality_ratio,4)
def accept_optimization(baseline_tokens,optimized_tokens,baseline_quality,optimized_quality,tolerance=0):
    return optimized_tokens<baseline_tokens and optimized_quality>=baseline_quality-tolerance
