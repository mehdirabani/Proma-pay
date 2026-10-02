#!/usr/bin/env python3
from pathlib import Path
import re, unicodedata, yaml, json
BASE=Path(__file__).resolve().parent.parent
REG=yaml.safe_load((BASE/'manifests/module-registry.yaml').read_text(encoding='utf-8'))
MOD={m['id']:m for m in REG['modules']}
def norm(s):
    s=unicodedata.normalize('NFKC',str(s).casefold())
    s=s.translate(str.maketrans({'ي':'ی','ك':'ک','ۀ':'ه','ة':'ه','‌':' ','أ':'ا','إ':'ا'}))
    s=re.sub(r"[^\w\u0600-\u06ff+.#/:-]+"," ",s,flags=re.UNICODE)
    return re.sub(r'\s+',' ',s).strip()
def detect_language(s):
    fa=len(re.findall(r'[\u0600-\u06ff]',s)); en=len(re.findall(r'[a-zA-Z]',s))
    if fa and en:return 'mixed'
    if fa:return 'fa'
    low=s.lower()
    finglish=sum(x in low.split() for x in REG.get('language_detection',{}).get('finglish_hints',[]))
    return 'finglish' if finglish>=2 else 'en'
def level_num(r): return int(str(r).replace('R','')) if str(r).startswith('R') else 0
