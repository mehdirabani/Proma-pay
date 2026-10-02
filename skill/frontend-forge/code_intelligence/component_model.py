import re
from .analyzer import analyze_source

def component_model(text):
    base=analyze_source(text)
    props=[]
    m=re.search(r'(?:function\s+\w+|const\s+\w+\s*=)\s*\(?\s*\{([^}]*)\}',text)
    if m:props=[x.strip().split(':')[0] for x in m.group(1).split(',') if x.strip()]
    return {'props':props,'hooks':base['hooks'],'children':base['jsx_components'],
      'events':sorted(set(re.findall(r'on([A-Z][A-Za-z]+)\s*=',text))),
      'conditional_rendering':bool(re.search(r'\?\s*<|&&\s*<',text)),
      'async_dependencies':base['side_effect_signals'],'accessibility_semantics':sorted(set(re.findall(r'aria-[a-z-]+|role=',text)))}
