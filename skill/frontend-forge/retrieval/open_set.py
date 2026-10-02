from pathlib import Path
from .bm25_graph import BM25GraphRetriever
from .confidence_v2 import OpenSetConfidenceModel
from .states import *
from repository_intelligence.inverted_index import tokens
class OpenSetTargetDiscovery:
    def __init__(self,index,confidence_model=None):self.index=index;self.nodes=index['nodes'];self.r=BM25GraphRetriever(index);self.model=confidence_model or OpenSetConfidenceModel()
    def discover(self,task):
        rows,metrics=self.r.search(task,limit=30)
        if not rows:return {'state':TARGET_NOT_FOUND,'status':TARGET_NOT_FOUND,'targets':[],'confidence':1.0,'abstained':True,'metrics':metrics}
        top=rows[0]['retrieval_score'];second=rows[1]['retrieval_score'] if len(rows)>1 else 0;margin=max(0,top-second)
        n=self.nodes[rows[0]['path']];sources=sum(1 for k in ('symbols','exports','string_literals','aria_labels','test_descriptions','reverse_imports','tests','styles','routes') if n.get(k))
        graph_cons=min(1,sum(bool(n.get(k)) for k in ('reverse_imports','tests','styles','routes'))/3)
        parser=.65 if self.index.get('parser_mode')=='REDUCED' else .85
        intent=.8 if len(tokens(task))>=3 else .55
        ambiguity=1.0 if second and margin<.07 else 0.0
        neg=rows[0].get('negative_penalty',0)
        raw=self.model.feature_score(top_score=top,margin=margin,evidence_sources=sources,graph_consistency=graph_cons,parser_confidence=parser,intent_certainty=intent,ambiguity=ambiguity,negative_penalty=neg)
        conf=self.model.calibrate(raw);state=self.model.status(conf,bool(ambiguity))
        # Open-set abstention: weak absolute evidence + low discriminative support must not select nearest component.
        discriminative=rows[0].get('idf_evidence',0)
        if top<self.model.not_found_score or (discriminative<.25 and top<max(self.model.not_found_score,.18)):
            state=TARGET_NOT_FOUND;conf=max(conf,.7);abstain=True
        else:abstain=state in {TARGET_NOT_FOUND,TARGET_LOW_CONFIDENCE};
        return {'state':state,'status':state,'targets':rows[:12],'retrieval_score':top,'confidence':round(conf,4),'raw_confidence':round(raw,4),'score_separation':round(margin,4),'abstained':abstain,'metrics':metrics}
