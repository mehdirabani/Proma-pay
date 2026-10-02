def refactor_policy(test_coverage,behavioral_invariants):
    if test_coverage<0.5:return {'status':'CHARACTERIZATION_TEST_REQUIRED','reason':'insufficient coverage'}
    if not behavioral_invariants:return {'status':'CHARACTERIZATION_TEST_REQUIRED','reason':'missing invariants'}
    return {'status':'PASS'}
def invariants(public_api=True,dom=True,routing=True):
    out=[]
    if public_api:out.append('same public API')
    if dom:out.append('same DOM semantics')
    if routing:out.append('same routing')
    out+=['same user behavior','same test behavior'];return out
