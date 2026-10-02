from abc import ABC,abstractmethod
import re,math

def tok(s):return [x for x in re.findall(r'[a-z0-9\u0600-\u06ff]+',(s or '').lower()) if len(x)>1]
class SemanticRetriever(ABC):
    name='base'
    @abstractmethod
    def search(self,query,candidates,limit=30):...
class LexicalRetriever(SemanticRetriever):
    name='LEXICAL_GRAPH'
    def search(self,query,candidates,limit=30):
        q=set(tok(query));rows=[]
        for c in candidates:
            text=' '.join([c.get('path',''),*c.get('symbols',[]),*c.get('exports',[]),*c.get('strings',[]),*c.get('routes',[])])
            t=set(tok(text));over=len(q&t);union=len(q|t) or 1
            score=over/union
            if score:rows.append((score,c))
        return [dict(x[1],semantic_score=round(x[0],4)) for x in sorted(rows,key=lambda x:-x[0])[:limit]]
class EmbeddingRetriever(SemanticRetriever):
    name='UNAVAILABLE'
    def search(self,*a,**k):raise RuntimeError('SEMANTIC_BACKEND_UNAVAILABLE')
class LLMAssistedRetriever(EmbeddingRetriever):pass
