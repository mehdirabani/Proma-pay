from pathlib import Path
from repository_intelligence.inverted_index import InvertedIndex,tokens
GENERIC={'saved','form','panel','item','page','section','component','screen','view','thing','بخش','صفحه','آیتم','فرم'}
DOMAIN_TERMS={
 'shipping':{'shipping','delivery','address','location','ارسال','آدرس','تحویل'},
 'keyboard':{'keyboard','tab','focus','navigation','کیبورد','فوکوس','تب'},
 'billing':{'billing','invoice','payment','پرداخت','صورتحساب'},
 'notification':{'notification','alert','اعلان','هشدار'},
}
def concepts(ts):return {d for d,v in DOMAIN_TERMS.items() if set(ts)&v}
class BM25GraphRetriever:
    def __init__(self,index):self.index=index;self.nodes=index['nodes'];self.inv=InvertedIndex(index)
    def search(self,query,limit=80):
        rows,metrics=self.inv.bm25(query,limit=limit*3);q=tokens(query);qc=concepts(q);out=[]
        maxbm=max([x['bm25_score'] for x in rows] or [1])
        for row in rows:
            rel=row['path'];n=self.nodes[rel];doc=tokens(' '.join([rel,*n.get('symbols',[]),*n.get('exports',[]),*n.get('string_literals',[]),*n.get('aria_labels',[]),*n.get('test_descriptions',[])]));dc=concepts(doc)
            bm=row['bm25_score']/maxbm if maxbm else 0
            discr=sum(self.inv.idf(t) for t in set(q)&set(doc) if t not in GENERIC)
            generic=sum(1 for t in set(q)&set(doc) if t in GENERIC)
            graph_sources=sum(bool(n.get(k)) for k in ('reverse_imports','tests','styles','routes'))/4
            contradiction=0.0
            if qc and dc and not qc&dc:contradiction=.35
            elif qc and not dc:contradiction=.18
            role_bonus=.10 if '/widgets/' in '/'+rel or '/components/' in '/'+rel else 0.0
            role_penalty=.14 if '/tests/' in '/'+rel or '.test.' in rel or '.spec.' in rel else .05 if any(x in rel.lower() for x in ['legacy','skeleton','helper','story','demo']) else 0.0
            score=.52*bm+.22*min(1,discr/4)+.12*graph_sources+role_bonus-.10*min(1,generic/3)-contradiction-role_penalty
            out.append({'path':rel,'retrieval_score':max(0,round(score,4)),'bm25':round(bm,4),'idf_evidence':round(discr,4),'graph_evidence':round(graph_sources,4),'negative_penalty':round(contradiction,4),'concepts':sorted(dc)})
        out.sort(key=lambda x:(-x['retrieval_score'],x['path']))
        return out[:limit],metrics
