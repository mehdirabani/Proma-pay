from .evidence import normalized_terms
RULES=[
 ('fixed/min-width causes overflow',{'overflow','mobile','narrow','min-width','width','viewport','بیرون','موبایل'},['inspect width/min-width and containing block']),
 ('white-space prevents wrapping',{'overflow','nowrap','white-space','wrap'},['inspect white-space and intrinsic content width']),
 ('state ownership/remount resets state',{'reset','state','remount','key','ریست'},['inspect owner, key and mount lifecycle']),
 ('effect dependency/stale closure',{'stale','effect','useeffect','dependency','closure'},['inspect effect reads/writes and dependency list']),
 ('missing accessible label',{'label','keyboard','aria','input','accessibility','دسترسی','کیبورد','تب'},['inspect semantic label association and focus order']),
 ('type contract mismatch',{'typescript','type','prop','ts2322','interface','تایپ'},['run typecheck and inspect prop/type declarations']),
 ('eager import inflates initial bundle',{'bundle','eager','import','performance','slow','lazy','سرعت'},['inspect import mode and bundle/load evidence']),
 ('route target/path mismatch',{'route','routing','path','link','navigation','مسیر'},['inspect route declaration and link consumer']),
 ('form control contract is incorrect',{'form','submit','button','input','validation','فرم'},['inspect form/control semantics and submit path']),
 ('async loading/state branch is incomplete',{'async','loading','fetch','promise','request','لود'},['inspect loading/error/data branches']),
 ('unnecessary client boundary',{'client','boundary','nextjs','static','use'},['inspect use client directive and client-only APIs']),
 ('arbitrary Tailwind pixel utility',{'tailwind','arbitrary','spacing','utility','pixel'},['inspect arbitrary utility and project spacing scale']),
 ('duplicate calculation',{'duplicate','duplicated','calculation','refactor','local'},['inspect repeated computation and behavioral invariants']),
]
def build_hypotheses(symptom,signals=None,evidence=None):
    signal_text=' '.join(str(x) for x in (signals or []));ev_text=' '.join(str(x.get('observation','')) for x in (evidence or []) if isinstance(x,dict))
    terms=normalized_terms(str(symptom)+' '+signal_text+' '+ev_text);hs=[]
    for title,vocab,checks in RULES:
        matches=terms&{x.lower() for x in vocab}
        if matches:hs.append({'hypothesis_id':'h-'+str(len(hs)+1),'hypothesis':title,'prior_score':round(len(matches)/max(1,len(vocab)),3),
                              'signal_matches':sorted(matches),'supporting_evidence':[],'contradicting_evidence':[],
                              'required_checks':checks,'confirm_query':checks,'reject_query':['collect contradicting repository/tool evidence']})
    if not hs:hs=[{'hypothesis_id':'h-1','hypothesis':'unknown root cause','prior_score':0.05,'signal_matches':[],
                   'supporting_evidence':[],'contradicting_evidence':[],'required_checks':['collect repository/reproduction evidence'],
                   'confirm_query':['collect direct observation'],'reject_query':[]}]
    return sorted(hs,key=lambda x:-x['prior_score'])
