def run_repair(validate,repair,max_cycles=3):
    trace=[]
    result=validate();trace.append({'cycle':0,'validation':result})
    if result.get('status')=='PASS':return {'status':'PASS','cycles':0,'trace':trace}
    for i in range(1,max_cycles+1):
        rr=repair(result,i);trace.append({'cycle':i,'repair':rr});result=validate();trace.append({'cycle':i,'validation':result})
        if result.get('status')=='PASS':return {'status':'PASS','cycles':i,'trace':trace}
    return {'status':'REPAIR_LIMIT_REACHED','cycles':max_cycles,'trace':trace}
