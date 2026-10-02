def choose_path(complexity,risk='LOW',confidence=.8):
    if complexity=='TRIVIAL' and risk=='LOW' and confidence>=.7:
        return {'path':'FAST_PATH','stages':['intent','target','direct_context','edit','minimal_validation']}
    return {'path':'DEEP_PATH','stages':['intent','index','impact','context','plan','execute','quality','regression']}
