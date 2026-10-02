import hashlib,re
CODE_EXT=('.js','.jsx','.ts','.tsx','.css','.scss','.html','.vue','.svelte')
def normalize_ws(text):return re.sub(r'\s+',' ',(text or '').strip())
def _is_code(c):
    p=str(c.get('path','')).lower()
    return p.endswith(CODE_EXT) or c.get('kind')=='code'
def _doc_signature(text):
    words=[w for w in re.findall(r'[\w-]+',normalize_ws(text).lower(),flags=re.UNICODE) if len(w)>2]
    return frozenset(words)
def deduplicate(chunks,semantic_threshold=.92):
    kept=[];dups=[];exact=set();normalized=set();docs=[]
    for c in chunks:
        text=c.get('text','');eh=hashlib.sha256(text.encode()).hexdigest();nh=hashlib.sha256(normalize_ws(text).encode()).hexdigest()
        if eh in exact or nh in normalized:dups.append({**c,'duplicate_reason':'exact/normalized-whitespace'});continue
        if not _is_code(c):
            sig=_doc_signature(text);isdup=False
            for prev in docs:
                u=len(sig|prev) or 1
                if len(sig&prev)/u>=semantic_threshold:isdup=True;break
            if isdup:dups.append({**c,'duplicate_reason':'document-semantic'});continue
            docs.append(sig)
        exact.add(eh);normalized.add(nh);kept.append(c)
    return {'kept':kept,'duplicates':dups}
