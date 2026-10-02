def token_regression(baseline_median,current_median,baseline_quality,current_quality,threshold=.20):
    increase=(current_median-baseline_median)/max(1,baseline_median)
    reg=increase>threshold and current_quality<=baseline_quality
    return {'status':'REGRESSION' if reg else 'PASS','increase_ratio':round(increase,4),'threshold':threshold,'quality_delta':current_quality-baseline_quality}

def saving_percentage(baseline,optimized):
    return round((1-(optimized/max(1,baseline)))*100,2)

def accept_optimization(baseline_tokens,optimized_tokens,baseline_quality,optimized_quality,tolerance=0.0):
    return optimized_tokens < baseline_tokens and optimized_quality >= baseline_quality-tolerance
