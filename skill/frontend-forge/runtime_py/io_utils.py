from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
def load_json(rel):
    return json.loads((ROOT/rel).read_text(encoding="utf-8"))
def import_callable(spec):
    import importlib
    module,name=spec.split(":",1)
    return getattr(importlib.import_module(module),name)
