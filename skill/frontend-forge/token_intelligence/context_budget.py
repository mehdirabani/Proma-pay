from .task_complexity import CLASSES
BASE={'TRIVIAL':.20,'SMALL':.32,'MEDIUM':.50,'LARGE':.68,'REPOSITORY_WIDE':.80}
def allocate(model_window,complexity,repository_size=0,uncertainty=.2,mode='BALANCED'):
    if complexity not in BASE:raise ValueError('unknown complexity')
    mode_factor={'TOKEN_SAVER':.82,'BALANCED':1.0,'QUALITY_FIRST':1.15}[mode]
    repo_factor=1.0+min(.15,max(0,repository_size-100)/10000)
    uncertainty_factor=1.0+min(.20,max(0,uncertainty-.2)*.35)
    usable=min(.88,BASE[complexity]*mode_factor*repo_factor*uncertainty_factor)
    total=int(model_window*usable)
    reserve=max(int(model_window*.08),int(total*.12))
    generation=int(total*.24);review=int(total*.14)
    context=max(0,total-reserve-generation-review)
    return {'status':'ESTIMATED','model_window':model_window,'complexity':complexity,'mode':mode,
            'context_tokens':context,'generation_tokens':generation,'review_tokens':review,'reserve_tokens':reserve,
            'allocated_tokens':context+generation+review+reserve,'window_utilization':round((context+generation+review+reserve)/model_window,4)}
