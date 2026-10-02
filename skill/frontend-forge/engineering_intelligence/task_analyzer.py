import re
CREATE=['implement','create','build ','add ','make a ','پیاده','بساز','ایجاد','اضافه کن']
REPAIR=['fix','repair','broken','bug','fails','overflow','wrong','خراب','رفع','اصلاح کن','درست کن']
REFACTOR=['refactor','extract','simplify','cleanup','بازآرایی','ریفکتور','duplicat']
REVIEW=['review','audit','inspect','بازبینی','بررسی کن']
DOMAINS={
 'responsive':['responsive','mobile','overflow','viewport','breakpoint','موبایل','ریسپانسیو','نمایشگر'],
 'css':['css','tailwind','padding','margin','grid','flex','spacing','فاصله','رنگ'],
 'accessibility':['accessibility','accessible','a11y','aria','keyboard','focus','label','دسترسی','قابل دسترس','کیبورد','تب'],
 'performance':['performance','slow','faster','lcp','bundle','rerender','سرعت','کند'],
 'typescript':['typescript','type error','ts2322','type mismatch','تایپ'],
 'react':['react','state','hook','useeffect','component','کامپوننت','ریست'],
 'nextjs':['next.js','nextjs','server component','app router','route handler'],
 'form':['form','checkout','payment','input','submit','فرم','پرداخت'],
 'routing':['route','router','navigation','link','مسیر','لینک'],
 'async-data':['async','fetch','loading','promise','query','لود','داده'],
 'seo':['seo','metadata','canonical','structured data','سئو']}
def _has(low,arr):return any(x in low for x in arr)
def analyze_task(task:str):
    text=task.strip();low=text.lower()
    # Operation is independent from domain. Creation wins over responsive/a11y vocabulary.
    if _has(low,CREATE):operation='CREATE_FEATURE'
    elif _has(low,REFACTOR):operation='REFACTOR'
    elif _has(low,REVIEW):operation='REVIEW'
    elif _has(low,REPAIR):operation='REPAIR'
    else:operation='ANALYZE'
    domains=sorted(d for d,s in DOMAINS.items() if _has(low,s))
    if operation=='REFACTOR':task_type='REFACTOR'
    elif operation=='CREATE_FEATURE':task_type='FEATURE'
    elif 'accessibility' in domains:task_type='ACCESSIBILITY_FIX'
    elif 'responsive' in domains:task_type='RESPONSIVE_FIX'
    elif 'performance' in domains:task_type='PERFORMANCE_FIX'
    elif 'typescript' in domains:task_type='TYPE_ERROR'
    elif 'seo' in domains:task_type='SEO_FIX'
    elif operation=='REPAIR':task_type='BUG_FIX'
    elif 'css' in domains:task_type='STYLE_CHANGE'
    else:task_type='CODE_REVIEW' if operation=='REVIEW' else 'BUG_FIX'
    target_type='page' if 'page' in low or 'صفحه' in low else 'form' if 'form' in domains else 'component'
    constraints=[]
    if _has(low,['do not','without changing','preserve','unchanged','تغییر نکند','حفظ']):constraints.append('preserve_existing_behavior')
    acceptance=[]
    if 'responsive' in domains:acceptance.append('responsive_behavior_correct')
    if 'accessibility' in domains:acceptance.append('accessible_behavior_correct')
    if 'typescript' in domains:acceptance.append('typecheck_passes')
    if 'routing' in domains:acceptance.append('route_behavior_correct')
    if 'form' in domains:acceptance.append('form_behavior_correct')
    if 'performance' in domains:acceptance.append('performance_change_validated')
    if not acceptance:acceptance=['requested_behavior_satisfied']
    risk='HIGH' if operation=='REFACTOR' or 'architecture' in low or 'معماری' in low else 'MEDIUM' if operation=='CREATE_FEATURE' or len(domains)>1 else 'LOW'
    return {'goal':text,'operation':operation,'task_type':task_type,'domains':domains,'target_type':target_type,'targets':[],
      'explicit_requirements':[text],'implicit_requirements':['no_unrelated_changes','preserve_project_conventions'],
      'constraints':constraints,'acceptance_criteria':acceptance,'unknowns':[],'risk':risk}
def task_analyzer_capability(ctx):return {'engineering_task':analyze_task(ctx['task'])}
