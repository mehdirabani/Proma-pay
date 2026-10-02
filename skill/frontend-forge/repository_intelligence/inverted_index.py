import re,math
from collections import Counter

def tokens(s):return [x for x in re.findall(r'[a-z0-9\u0600-\u06ff]+',(s or '').lower()) if len(x)>1]
class InvertedIndex:
    def __init__(self,index):
        self.nodes=index['nodes'];idx=index.get('indexes',{});pre=idx.get('token',{})
        self.postings={k:set(v) for k,v in pre.items()} if pre else {};self.symbol={k:set(v) for k,v in idx.get('symbol',{}).items()};self.route={k:set(v) for k,v in idx.get('route',{}).items()};self.component={};self.doc_lengths={};self.term_freqs={};self.df={};self.avgdl=0.0
        self._build_stats(rebuild_postings=not bool(self.postings))
    def _add(self,m,k,v):m.setdefault(k,set()).add(v)
    def _node_terms(self,rel,n):
        fields=[rel,*n.get('symbols',[]),*n.get('exports',[]),*n.get('jsx_components',[]),*n.get('string_literals',[]),*n.get('aria_labels',[]),*n.get('test_descriptions',[])]
        return tokens(' '.join(fields))
    def _build_stats(self,rebuild_postings=False):
        if rebuild_postings:self.postings={}
        total=0
        for rel,n in self.nodes.items():
            ts=self._node_terms(rel,n);cnt=Counter(ts);self.term_freqs[rel]=cnt;self.doc_lengths[rel]=max(1,sum(cnt.values()));total+=self.doc_lengths[rel]
            if rebuild_postings:
                for t in cnt:self._add(self.postings,t,rel)
            for s in n.get('symbols',[])+n.get('exports',[]):self._add(self.symbol,s.lower(),rel)
            for r in n.get('routes',[]):self._add(self.route,r.lower(),rel)
            if '/components/' in '/'+rel or '/widgets/' in '/'+rel:self._add(self.component,rel.rsplit('/',1)[-1].split('.')[0].lower(),rel)
        self.avgdl=total/max(1,len(self.nodes));self.df={t:len(v) for t,v in self.postings.items()}
    def idf(self,t):
        n=max(1,len(self.nodes));df=self.df.get(t,0);return math.log(1+(n-df+.5)/(df+.5))
    def bm25(self,query,limit=100,k1=1.2,b=.75):
        q=tokens(query);scores={};lookups=0
        # Very high document-frequency terms carry little information and can dominate latency.
        informative=[t for t in q if self.df.get(t,0)>0 and self.df.get(t,0)/max(1,len(self.nodes))<.45]
        active=informative or q
        for t in active:
            lookups+=1;idf=self.idf(t)
            for rel in self.postings.get(t,set()):
                tf=self.term_freqs.get(rel,{}).get(t,0);dl=self.doc_lengths.get(rel,1);denom=tf+k1*(1-b+b*dl/max(self.avgdl,1e-9));scores[rel]=scores.get(rel,0)+idf*((tf*(k1+1))/max(denom,1e-9))
        rows=sorted(scores.items(),key=lambda kv:(-kv[1],kv[0]))[:limit]
        return [{'path':r,'bm25_score':s} for r,s in rows],{'index_lookups':lookups,'index_nodes_considered':len(rows),'candidate_generation_ms':0.0}
    def preselect(self,query,limit=100):
        rows,m=self.bm25(query,limit);return [x['path'] for x in rows],m
    def update_document(self,rel,node):
        # O(terms in changed document + affected postings), not O(repository).
        old=set(self.term_freqs.get(rel,{}))
        for t in old:
            s=self.postings.get(t)
            if s:s.discard(rel)
            if s is not None and not s:self.postings.pop(t,None)
        if node is None:
            self.nodes.pop(rel,None);self.term_freqs.pop(rel,None);self.doc_lengths.pop(rel,None)
        else:
            self.nodes[rel]=node;cnt=Counter(self._node_terms(rel,node));self.term_freqs[rel]=cnt;self.doc_lengths[rel]=max(1,sum(cnt.values()))
            for t in cnt:self._add(self.postings,t,rel)
        self.df={t:len(v) for t,v in self.postings.items()};self.avgdl=sum(self.doc_lengths.values())/max(1,len(self.doc_lengths));return {'status':'PASS','updated':rel,'old_terms':len(old),'new_terms':len(self.term_freqs.get(rel,{}))}
