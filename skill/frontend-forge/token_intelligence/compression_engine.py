import json,re

def _ts_clusters(output,max_examples=3):
    lines=[x for x in output.splitlines() if 'error TS' in x];clusters={}
    for line in lines:
        m=re.search(r'error (TS\d+):\s*(.*)',line);key=m.group(1) if m else 'UNKNOWN';c=clusters.setdefault(key,{'error_type':key,'count':0,'affected_files':set(),'examples':[]})
        c['count']+=1;fm=re.match(r'([^(:]+)',line)
        if fm:c['affected_files'].add(fm.group(1))
        if len(c['examples'])<max_examples:c['examples'].append(line[:300])
    return [{**v,'affected_files':sorted(v['affected_files'])} for v in clusters.values()],lines

def compress_typescript(output,max_examples=3):
    rows,_=_ts_clusters(output,max_examples)
    return rows

def compress_typescript_detailed(output,max_examples=3):
    rows,lines=_ts_clusters(output,max_examples)
    if not lines and output.strip():return {'status':'FALLBACK_RAW','raw_relevant':output[-4000:],'clusters':[],'confidence':.2,'source_findings':0,'preserved_findings':0}
    return {'status':'COMPRESSED','clusters':rows,'confidence':.95,'source_findings':len(lines),'preserved_findings':sum(x['count'] for x in rows)}

def compress_logs(text):
    return [line[:500] for line in text.splitlines() if any(x in line.upper() for x in ['ERROR','WARNING','WARN','FAILED','STATE','TRANSITION'])]

def compress_logs_detailed(text):
    important=compress_logs(text)
    return {'status':'COMPRESSED' if important else 'FALLBACK_RAW','important':important,'raw_relevant':'' if important else text[-3000:]}

def compress_lighthouse(report):
    if isinstance(report,str):report=json.loads(report)
    cats=report.get('categories',{});aud=report.get('audits',{})
    out={'status':'COMPRESSED','scores':{k:round(v.get('score',0)*100,1) for k,v in cats.items() if isinstance(v,dict) and v.get('score') is not None},'regressions':[]}
    for key,target in [('largest-contentful-paint',2500),('cumulative-layout-shift',.1),('interaction-to-next-paint',200)]:
        a=aud.get(key,{});val=a.get('numericValue')
        if val is not None and val>target:out['regressions'].append({'metric':key,'value':val,'target':target})
    return out
