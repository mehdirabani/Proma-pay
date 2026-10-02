from adapters.registry import get_adapter
def execute_with_fallback(adapter_names,context,mode="LOCAL",timeout=60):
    attempts=[]
    for name in adapter_names:
        adapter=get_adapter(name)
        if not adapter.available():
            attempts.append({"adapter":name,"status":"TOOL_UNAVAILABLE"})
            continue
        result=adapter.execute(context,mode=mode,timeout=timeout)
        attempts.append({"adapter":name,"status":result["status"]})
        if result["status"]=="PASS":
            return {"status":"PASS","selected":name,"result":result,"attempts":attempts}
    return {"status":"PARTIAL","selected":None,"validation":"REDUCED","attempts":attempts}
