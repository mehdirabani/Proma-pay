#!/usr/bin/env python3
from pathlib import Path
import json,yaml,sys,hashlib,os,re
BASE=Path(__file__).resolve().parent.parent;errors=[];warnings=[]
def err(x):errors.append(x)
# exactly one skill
skills=list(BASE.rglob('SKILL.md'))
if len(skills)!=1:err('Exactly one SKILL.md required, got '+str(len(skills)))
for d in ['core','router','modules','workflows','references','scripts','evals','benchmarks','schemas','manifests','tests']:
 if not (BASE/d).is_dir():err('missing dir '+d)
# yaml/json validity
for p in BASE.rglob('*'):
 if not p.is_file():continue
 try:
  if p.suffix in ['.yaml','.yml']:yaml.safe_load(p.read_text(encoding='utf-8'))
  elif p.suffix=='.json':json.loads(p.read_text(encoding='utf-8'))
 except Exception as e:err(f'invalid {p.relative_to(BASE)}: {e}')
reg=yaml.safe_load((BASE/'manifests/module-registry.yaml').read_text(encoding='utf-8'));mods=reg['modules'];ids=[m['id'] for m in mods]
if len(ids)!=len(set(ids)):err('duplicate module ids')
known=set(ids)
for m in mods:
 if not (BASE/m['file']).exists():err('broken module file '+m['file'])
 for d in m.get('dependencies',{}).get('required',[]):
  if d not in known:err('missing dependency '+d)
# cycle
G={m['id']:m.get('dependencies',{}).get('required',[]) for m in mods};seen=set();stack=set()
def visit(x):
 if x in stack:err('dependency cycle '+x);return
 if x in seen:return
 stack.add(x)
 for y in G[x]:visit(y)
 stack.remove(x);seen.add(x)
for x in G:visit(x)
# version consistency
skill=yaml.safe_load((BASE/'skill.yaml').read_text(encoding='utf-8'))
if skill.get('version')!='1.2.0' or reg.get('runtime_version')!='1.2.0':err('version mismatch')
if '1.2.0' not in (BASE/'CHANGELOG.md').read_text(encoding='utf-8'):err('changelog version missing')
# release hygiene
bad=[]
for p in BASE.rglob('*'):
 if '__pycache__' in p.parts or p.suffix in ['.pyc','.pyo'] or p.name in ['.coverage','.env','.env.local','.env.production'] or '.pytest_cache' in p.parts:bad.append(str(p.relative_to(BASE)))
if bad:err('release hygiene: '+str(bad[:10]))
# scripts executable source present (zip permission may vary, validate shebang)
for p in (BASE/'scripts').glob('*.py'):
 if not p.read_text(encoding='utf-8').startswith('#!'):err('script missing shebang '+p.name)
# workflow references from registry must resolve
for key,path in (reg.get('workflow_map') or {}).items():
 if not (BASE/path).exists():err(f'broken workflow reference {key}: {path}')
# dataset counts, category coverage and uniqueness
summary=json.loads((BASE/'evals/dataset-summary.json').read_text(encoding='utf-8')) if (BASE/'evals/dataset-summary.json').exists() else {}
if summary.get('total',0)<1200:err('dataset <1200')
if summary.get('normalized_unique')!=summary.get('total'):err('dataset not 100% normalized unique')
minimums={'general':150,'php-core':70,'laravel':100,'symfony':80,'wordpress':100,'woocommerce':120,'security':180,'fintech':180,'database':100,'performance':70,'testing':60,'legacy':50,'context':80,'negation':70,'ood':100,'adversarial':100}
for cat,n in minimums.items():
 if summary.get('category_counts',{}).get(cat,0)<n:err(f'dataset category {cat} below minimum {n}')
# checksum manifest, when present, must cover deterministic release files and verify their current bytes.
cp=BASE/'manifests/checksums.json'
if cp.exists():
 data=json.loads(cp.read_text(encoding='utf-8'))
 for rel,digest in data.get('files',{}).items():
  fp=BASE/rel
  if not fp.exists():err('checksum file missing '+rel);continue
  got=hashlib.sha256(fp.read_bytes()).hexdigest()
  if got!=digest:err('checksum mismatch '+rel)
status='PASS' if not errors else 'FAIL';out={'status':status,'errors':errors,'warnings':warnings,'skill_count':len(skills),'module_count':len(mods),'dataset_cases':summary.get('total')};print(json.dumps(out,ensure_ascii=False,indent=2));sys.exit(0 if status=='PASS' else 1)
