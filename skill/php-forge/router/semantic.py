#!/usr/bin/env python3
from .common import REG,norm
from functools import lru_cache
import math

def _exact(task, concept):
    hits=[]
    for lang,vals in (concept.get('aliases') or {}).items():
        for v in vals:
            nv=norm(v)
            if nv and nv in task: hits.append(v)
    return hits

@lru_cache(maxsize=1)
def _lsa_model():
    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
        from sklearn.pipeline import FeatureUnion
        from sklearn.decomposition import TruncatedSVD
        from sklearn.preprocessing import Normalizer
        from sklearn.metrics.pairwise import cosine_similarity
        import numpy as np
    except Exception:
        return None
    ids=[]; docs=[]
    for cid,c in REG['concepts'].items():
        vals=[cid.replace('_',' '),c.get('description','')]
        for xs in (c.get('aliases') or {}).values(): vals.extend(xs)
        docs.append(' '.join(vals)); ids.append(cid)
    # Word and character features reduce spelling sensitivity; SVD supplies a latent semantic layer over bilingual concept documents.
    vec=FeatureUnion([
      ('w',TfidfVectorizer(ngram_range=(1,2),min_df=1,sublinear_tf=True)),
      ('c',TfidfVectorizer(analyzer='char_wb',ngram_range=(3,5),min_df=1,sublinear_tf=True,max_features=12000))
    ])
    X=vec.fit_transform(docs)
    n=max(2,min(int(REG['semantic_engine'].get('lsa_dimensions',24)),X.shape[0]-1,X.shape[1]-1))
    svd=TruncatedSVD(n_components=n,random_state=17)
    Z=Normalizer(copy=False).fit_transform(svd.fit_transform(X))
    return ids,vec,svd,Z,cosine_similarity

def extract_concepts(task):
    t=norm(task); out={}
    # Layer A: deterministic safety/concept rules from registry data.
    for cid,c in REG['concepts'].items():
        hits=_exact(t,c)
        if hits:
            out[cid]={'heuristic_score':1.0 if c.get('safety_critical') else .9,'confidence':'confirmed' if c.get('safety_critical') else 'high','method':'registry-alias','evidence':hits[:4]}
    # Layer B: latent semantic fallback. Exact registry evidence wins; latent inference is deliberately sparse.
    model=_lsa_model() if not out else None
    if model:
        ids,vec,svd,Z,cosine_similarity=model
        q=svd.transform(vec.transform([task]));
        import numpy as np
        nq=np.linalg.norm(q)
        if nq>0:
            q=q/nq; sims=cosine_similarity(q,Z)[0]
            ranked=sorted(zip(ids,sims),key=lambda x:float(x[1]),reverse=True)
            if ranked:
                cid,score=ranked[0]
                threshold=0.82 if out else 0.50
                if float(score)>=threshold and cid not in out:
                    out[cid]={'heuristic_score':round(float(score),4),'confidence':'high' if score>=0.72 else 'medium','method':'latent-semantic','evidence':['top latent concept prototype similarity']}
    return out
