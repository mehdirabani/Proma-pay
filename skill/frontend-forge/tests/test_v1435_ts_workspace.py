import unittest,tempfile,json
from pathlib import Path
from repository_intelligence.tsconfig_resolver import ProjectResolver,ConfigCycle
class T(unittest.TestCase):
 def test_workspace_package_types_source(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'packages/ui/src').mkdir(parents=True);(r/'apps/web').mkdir(parents=True)
   (r/'package.json').write_text(json.dumps({'workspaces':['packages/*','apps/*']}));(r/'packages/ui/package.json').write_text(json.dumps({'name':'@co/ui','exports':{'.':{'types':'./src/index.ts','import':'./dist/index.js'}}}));(r/'packages/ui/src/index.ts').write_text('export const Button=1');src=r/'apps/web/a.ts';src.write_text("import {Button} from '@co/ui'")
   self.assertEqual(ProjectResolver(r).resolve(src,'@co/ui'),'packages/ui/src/index.ts')
 def test_nested_tsconfig_paths(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'packages/a/src').mkdir(parents=True);(r/'shared').mkdir();(r/'shared/u.ts').write_text('export const u=1');(r/'packages/a/tsconfig.json').write_text(json.dumps({'compilerOptions':{'baseUrl':'../..','paths':{'#shared/*':['shared/*']}}}));src=r/'packages/a/src/x.ts';src.write_text('x')
   self.assertEqual(ProjectResolver(r).resolve(src,'#shared/u'),'shared/u.ts')
