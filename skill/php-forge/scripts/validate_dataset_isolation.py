#!/usr/bin/env python3
from pathlib import Path
import json,re,sys,itertools
BASE=Path(__file__).resolve().parent.parent

def norm(s):return ' '.join(re.sub(r'[^\w\u0600-\u06ff]+',' ',s.casefold()).split())
def load(split):
 p=BASE/'evals'/split/'cases.jsonl';return [json.loads(x) for x in p.read_text(encoding='utf-8').splitlines() if x.strip()]
sets={s:load(s) for s in ['development','validation','hidden']}
errors=[];stats={}
for a,b in itertools.combinations(sets,2):
 na={norm(x['task']) for x in sets[a]};nb={norm(x['task']) for x in sets[b]}; ex=na&nb
 ga={x['concept_group'] for x in sets[a]};gb={x['concept_group'] for x in sets[b]}; cg=ga&gb
 stats[a+'__'+b]={'exact_or_normalized_overlap':len(ex),'concept_group_overlap':len(cg)}
 if ex:errors.append(f'normalized overlap {a}/{b}: {len(ex)}')
 if cg:errors.append(f'concept group overlap {a}/{b}: {len(cg)}')
# near duplicate cross-split using character TF-IDF; high threshold intentionally strict
try:
 from sklearn.feature_extraction.text import TfidfVectorizer
 from sklearn.metrics.pairwise import cosine_similarity
 for a,b in itertools.combinations(sets,2):
  A=[norm(x['task']) for x in sets[a]];B=[norm(x['task']) for x in sets[b]]
  v=TfidfVectorizer(analyzer='char_wb',ngram_range=(3,5),min_df=1,max_features=40000);X=v.fit_transform(A+B)
  S=cosine_similarity(X[:len(A)],X[len(A):]); mx=float(S.max()) if S.size else 0
  pairs=int((S>=.965).sum());stats[a+'__'+b]['near_duplicate_pairs_ge_0.965']=pairs;stats[a+'__'+b]['max_similarity']=round(mx,4)
  if pairs:errors.append(f'near duplicate pairs {a}/{b}: {pairs}')
except Exception as e: errors.append('semantic similarity check unavailable: '+str(e))
allrows=sum(sets.values(),[]);allnorm=[norm(x['task']) for x in allrows]
stats['total']={'rows':len(allrows),'unique_normalized':len(set(allnorm)),'unique_ratio':len(set(allnorm))/max(len(allrows),1)}
status='PASS' if not errors else 'FAIL';out={'status':status,'errors':errors,'stats':stats}
(BASE/'reports'/'eval-integrity.json').write_text(json.dumps(out,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(out,ensure_ascii=False,indent=2));sys.exit(0 if status=='PASS' else 1)
