UNITS={'provider_tokens','tokenizer_tokens','estimated_tokens','bytes','milliseconds','MB','count','ratio'}
def metric(name,value,unit,method):
    if unit not in UNITS:raise ValueError('INVALID_METRIC_UNIT')
    return {'metric':name,'value':value,'unit':unit,'method':method}
