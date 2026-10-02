import re,math
from pathlib import Path
from repository_intelligence.inverted_index import InvertedIndex,tokens as inv_tokens
from .semantic import LexicalRetriever
from .confidence import EmpiricalConfidenceCalibrator
from .states import *

# Small domain dictionary is only intent assistance; target semantics come from repository evidence.
LANG_EQUIV={
 'product':{'product','محصول','کالا'},
 'card':{'card','کارت','آیتم'},
 'navigation':{'navigation','navbar','nav','منو','ناوبری','سربرگ','header'},
 'checkout':{'checkout','payment','پرداخت','تسویه'},
}
DOMAIN_HINTS={
 'accessibility':{'keyboard','tab','focus','label','aria','دسترسی','کیبورد','فوکوس'},
 'performance':{'slow','fast','speed','loading','lcp','performance','کند','سرعت','لود'},
 'style':{'overflow','spacing','padding','layout','responsive','viewport','بیرون','فاصله','موبایل','نمایشگر'},
 'navigation':{'header','navigation','nav','links','menu','هدر','منو','لینک'},
}
STOP={'fix','change','make','the','this','in','on','for','screen','page','better','repair','رفع','اصلاح','کن','در','روی','برای','می','شود','cannot'}
def norm(s):return re.sub(r'[\u200c_\-]+',' ',str(s).lower())
def toks(s):return {x for x in re.findall(r'[a-z0-9\u0600-\u06ff]+',norm(s)) if len(x)>1 and x not in STOP}

def _domain_tokens(task):
    tt=toks(task);return {d for d,v in DOMAIN_HINTS.items() if tt&v}
def _equiv_groups(ts):
    return {k for k,v in LANG_EQUIV.items() if ts&v}

class TargetDiscoveryEngine:
    def __init__(self,index,calibrator=None):
        self.index=index;self.nodes=index['nodes'];self.inverted=InvertedIndex(index);self.calibrator=calibrator
    def _metadata(self,rel,n):
        return {'path':rel,'symbols':n.get('symbols',[]),'exports':n.get('exports',[]),'strings':n.get('string_literals',[])+n.get('aria_labels',[])+n.get('test_descriptions',[]),'routes':n.get('routes',[])}
    def discover(self,task,changed_files=()):
        q=toks(task);domains=_domain_tokens(task);pre,pre_metrics=self.inverted.preselect(task,limit=120)
        # If lexical postings are empty, use component metadata but do not silently claim high certainty.
        pool=pre or [r for r in self.nodes if '/components/' in '/'+r][:120]
        semantic=LexicalRetriever().search(task,[self._metadata(r,self.nodes[r]) for r in pool],limit=80)
        semantic_by={x['path']:x.get('semantic_score',0) for x in semantic}
        scores=[]
        for rel in pool:
            n=self.nodes[rel];pt=toks(rel+' '+Path(rel).stem);symbols=set();strings=set()
            for x in n.get('symbols',[])+n.get('exports',[])+n.get('jsx_components',[]):symbols|=toks(x)
            for x in n.get('string_literals',[])+n.get('aria_labels',[])+n.get('test_descriptions',[]):strings|=toks(x)
            signals=[];contrib={};score=0.0
            symbol_exact=q&symbols;path_exact=q&pt
            if symbol_exact:
                contrib['symbol_exact']=min(.62,.46+.06*len(symbol_exact));score+=contrib['symbol_exact'];signals.append('symbol-exact')
            elif path_exact:
                contrib['path_exact']=min(.38,.24+.04*len(path_exact));score+=contrib['path_exact'];signals.append('path-exact')
            ui=q&strings
            if ui:contrib['repository_ui_string']=min(.36,.12+.05*len(ui));score+=contrib['repository_ui_string'];signals.append('ui/test-string')
            # Minimal language normalization for common engineering/UI nouns; holdout-only vocabulary is deliberately absent.
            eq=_equiv_groups(q)&_equiv_groups(pt|symbols|strings)
            if eq:contrib['language_normalization']=min(.18,.08*len(eq));score+=contrib['language_normalization'];signals.append('language-normalization')
            sem=semantic_by.get(rel,0)
            if sem:contrib['lexical_repository_semantics']=min(.20,sem*.7);score+=contrib['lexical_repository_semantics'];signals.append('repository-semantic')
            if rel in changed_files:contrib['git_changed']=.10;score+=.10;signals.append('changed-file')
            if '/components/' in '/'+rel and score>0:contrib['component_role']=.12;score+=.12;signals.append('component-role')
            if ('test' in rel.lower() or 'spec' in rel.lower()) and score>0:contrib['test_role_penalty']=-.06;score-=.06
            # repository graph support: a component with consumers/tests/styles gets independent evidence.
            graph_sources=sum(bool(n.get(k)) for k in ('reverse_imports','tests','styles','routes'))
            if score>0 and graph_sources:contrib['graph_consistency']=min(.12,.03*graph_sources);score+=contrib['graph_consistency'];signals.append('graph')
            # domain evidence is only applied if node relationships support it.
            if 'accessibility' in domains and (n.get('tests') or n.get('aria_labels')):contrib['intent_domain']=.05;score+=.05
            if 'style' in domains and n.get('styles'):contrib['intent_domain']=max(contrib.get('intent_domain',0),.05);score+=.05
            if score>0:scores.append({'path':rel,'symbol':(n.get('symbols') or n.get('exports') or [None])[0],'retrieval_score':round(min(score,1),4),'score':round(min(score,1),4),'signals':signals,'score_contribution':contrib})
        scores.sort(key=lambda x:(-x['retrieval_score'],x['path']))
        if not scores:return {'status':'AMBIGUOUS_TARGET','state':TARGET_AMBIGUOUS,'targets':[],'confidence':0.0,'calibrated_confidence':0.0,'evidence':[],'metrics':pre_metrics}
        top=scores[0]['retrieval_score'];second=scores[1]['retrieval_score'] if len(scores)>1 else 0;margin=max(0,top-second)
        evidence_sources=len(set(scores[0]['signals']));parser_conf=.72 if self.index.get('parser_mode')=='REDUCED' else .88
        raw=max(0,min(.99,.48*top+.24*margin+.06*evidence_sources+.12*parser_conf))
        calibrated=self.calibrator.calibrate(raw) if self.calibrator else raw
        ambiguity=(second>0 and margin<.08)
        if calibrated>=.62 and not ambiguity:state=TARGET_RESOLVED;legacy='PASS'
        elif calibrated>=.28:state=CONTEXT_EXPANSION_REQUIRED;legacy='EXPAND_CONTEXT'
        else:state=TARGET_AMBIGUOUS;legacy='AMBIGUOUS_TARGET'
        return {'status':legacy,'state':state,'targets':scores[:12],'confidence':round(calibrated,4),'calibrated_confidence':round(calibrated,4),'raw_confidence':round(raw,4),'evidence':scores[0]['signals'],'score_separation':round(margin,4),'parser_mode':self.index.get('parser_mode'),'metrics':pre_metrics}
