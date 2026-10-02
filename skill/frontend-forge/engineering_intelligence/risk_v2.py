def score_risk(*,files_affected=1,centrality=0.1,public_api=False,state_change=False,routing_change=False,data_change=False,test_coverage=1.0,retrieval_state='TARGET_CONFIRMED'):
    s=min(files_affected/10,1)*20 + min(max(centrality,0),1)*20
    s+=20 if public_api else 0;s+=12 if state_change else 0;s+=12 if routing_change else 0;s+=10 if data_change else 0
    s+=max(0,1-test_coverage)*20
    s+= {'TARGET_CONFIRMED':0,'TARGET_PROBABLE':15,'TARGET_AMBIGUOUS':30,'TARGET_NOT_FOUND':50}.get(retrieval_state,30)
    s=min(round(s,2),100)
    level='LOW' if s<25 else 'MEDIUM' if s<50 else 'HIGH' if s<75 else 'CRITICAL'
    return {'score':s,'level':level}
