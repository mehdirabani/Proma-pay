def derive_invariants(task_model):
    domains=set(task_model.get('domains',[]));must_preserve=['unrelated behavior','public interfaces','project conventions']
    if 'responsive' in domains:must_preserve+=['desktop behavior','product/data semantics','interaction behavior']
    if 'accessibility' in domains:must_preserve+=['visual intent','existing keyboard behaviors not targeted']
    return {'must_change':task_model.get('acceptance_criteria',[]),'must_preserve':must_preserve,'must_not_change':['unrelated files','disabled quality gates']}
