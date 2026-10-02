import re

def trace_simple(text,source_symbol):
    edges=[]
    for line_no,line in enumerate(text.splitlines(),1):
        if source_symbol in line:
            assigns=re.findall(r'(\w+)\s*=\s*[^;]*'+re.escape(source_symbol),line)
            calls=re.findall(r'(\w+)\([^)]*'+re.escape(source_symbol),line)
            for x in assigns:edges.append({'from':source_symbol,'to':x,'kind':'assignment','line':line_no})
            for x in calls:edges.append({'from':source_symbol,'to':x,'kind':'call-argument','line':line_no})
    return {'source':source_symbol,'edges':edges,'mode':'REDUCED_STATIC'}
