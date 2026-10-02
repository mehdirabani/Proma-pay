import re
from .task_analyzer import DOMAINS
def _contains(low,items):return any(x in low for x in items)
def analyze_task_v3(task):
    text=task.strip();low=text.lower()
    repair_markers=['fix','repair','resolve','broken','bug','overflow','wrong','failure','fails','رفع','اصلاح','مشکل','خراب','درست']
    add=['add','اضافه'];remove=['remove','delete','حذف'];implement=['implement','create','build','بساز','ایجاد','پیاده']
    refactor=['refactor','extract','simplify','بازآرایی','ریفکتور'];review=['review','audit','بررسی','بازبینی']
    # "add X to fix Y" is semantically REPAIR, with edit modifier ADD.
    operation='REPAIR' if _contains(low,repair_markers) else 'REFACTOR' if _contains(low,refactor) else 'REVIEW' if _contains(low,review) else 'IMPLEMENT' if _contains(low,implement+add) else 'REMOVE' if _contains(low,remove) else 'ANALYZE'
    modifier='ADD' if _contains(low,add) else 'DELETE' if _contains(low,remove) else 'MODIFY'
    domains=sorted(d for d,s in DOMAINS.items() if _contains(low,s))
    target_type='page' if 'page' in low or 'صفحه' in low else 'form' if 'form' in domains else 'component'
    risk='HIGH' if _contains(low,['architecture','auth','migration','global state','معماری']) else 'LOW' if _contains(low,['padding','margin','color','رنگ','فاصله']) else 'MEDIUM'
    task_type='BUG_FIX' if operation=='REPAIR' else 'REFACTOR' if operation=='REFACTOR' else 'FEATURE' if operation=='IMPLEMENT' else 'CODE_REVIEW'
    criteria=[]
    for d in domains:
        criteria.append({
          'responsive':'responsive_behavior_correct','accessibility':'accessible_behavior_correct','performance':'performance_behavior_improved',
          'typescript':'typecheck_passes','routing':'route_behavior_correct','form':'form_behavior_correct','css':'visual_style_correct',
          'nextjs':'nextjs_behavior_correct','async-data':'async_state_behavior_correct'
        }.get(d,f'{d}_behavior_correct'))
    return {'goal':text,'operation':operation,'edit_intent':modifier,'domains':domains,'target_type':target_type,'task_type':task_type,
            'explicit_requirements':[text],'implicit_requirements':['preserve unrelated behavior'],'constraints':[],
            'acceptance_criteria':list(dict.fromkeys(criteria or ['requested_behavior_correct'])),'unknowns':[],'risk':risk,'analysis_mode':'RULE_BASED'}
