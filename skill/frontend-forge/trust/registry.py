from pathlib import Path
import json,hashlib
ROOT=Path(__file__).resolve().parents[1]
def validate_parser(i):
 r=json.loads((ROOT/"trust/parser-registry.json").read_text()).get(i)
 if not r:return False,"UNTRUSTED_PARSER"
 h=hashlib.sha256((ROOT/"evidence/parsers.py").read_bytes()).hexdigest();return (h==r["code_hash"],"PASS" if h==r["code_hash"] else "PARSER_IDENTITY_MISMATCH")
def validate_adapter(i,p):
 r=json.loads((ROOT/"trust/adapter-registry.json").read_text()).get(i)
 if not r:return False,"UNTRUSTED_ADAPTER"
 h=hashlib.sha256(Path(p).read_bytes()).hexdigest();return (h==r["code_hash"],"PASS" if h==r["code_hash"] else "ADAPTER_IDENTITY_MISMATCH")
