DOMAIN_REL={'TASK_TARGET_LINK':.85,'DEPENDENCY_RELATIONSHIP':.55,'SYMBOL_DEFINITION':.35,'ROOT_CAUSE_OBSERVATION':.45,'REPRODUCTION':.75}
def relevance(task_context,raw):
 meta=raw.get('metadata') or {};claim=raw.get('claim_type');score=DOMAIN_REL.get(claim,.2);rel=meta.get('relationship')
 if claim=='TASK_TARGET_LINK':score=.95 if meta.get('task_entities') else .05
 if claim=='DEPENDENCY_RELATIONSHIP':score=.65 if any(meta.get('relationships',{}).values()) else .15
 if claim=='ROOT_CAUSE_OBSERVATION':
  td=set(task_context.get('domains') or []);fd={x.get('domain') for x in meta.get('findings',[])};score=.85 if td&fd else .15
 if claim=='REPRODUCTION':score=.95 if meta.get('reproduced') else .1
 return {'score':round(score,4),'relationship':rel,'task_linking':claim=='TASK_TARGET_LINK' and bool(meta.get('task_entities')),'structured':True}
