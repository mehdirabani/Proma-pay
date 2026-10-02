def claim_text(level,value,scope):
    if level=='PROVIDER_MEASURED':return f'{value:.2f}% provider-measured token reduction on {scope}'
    if level=='TOKENIZER_MEASURED_FIXTURE':return f'{value:.2f}% tokenizer-measured context reduction on {scope}'
    if level=='REAL_REPOSITORY_ESTIMATED':return f'{value:.2f}% estimated context reduction on real repository benchmark {scope}'
    return f'{value:.2f}% estimated context reduction on maintained fixtures ({scope})'
