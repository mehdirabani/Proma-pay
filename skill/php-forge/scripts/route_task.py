#!/usr/bin/env python3
import argparse,json,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parent.parent))
from router.engine import route
ap=argparse.ArgumentParser();ap.add_argument('--task',required=True);ap.add_argument('--repo-info');a=ap.parse_args()
repo=json.loads(Path(a.repo_info).read_text(encoding='utf-8')) if a.repo_info else None
print(json.dumps(route(a.task,repo),ensure_ascii=False,indent=2))
