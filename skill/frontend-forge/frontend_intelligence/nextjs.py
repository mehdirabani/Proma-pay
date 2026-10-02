def analyze_nextjs(path,text):
    findings=[]
    client=text.lstrip().startswith("'use client'") or text.lstrip().startswith('"use client"')
    if client and not any(x in text for x in ['useState','useEffect','useContext','onClick=','window.','document.']):findings.append({'kind':'possibly_unnecessary_use_client'})
    router='app' if '/app/' in path.replace('\\','/') else 'pages' if '/pages/' in path.replace('\\','/') else 'unknown'
    return {'router':router,'client_component':client,'findings':findings}
