import unittest,tempfile,json
from pathlib import Path
from repository_intelligence.repo_indexer import RepositoryIndexer
class Alias(unittest.TestCase):
 def test_tsconfig_alias(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src/components').mkdir(parents=True);(r/'src/pages').mkdir(parents=True)
   (r/'tsconfig.json').write_text(json.dumps({'compilerOptions':{'baseUrl':'.','paths':{'@/*':['src/*']}}}))
   (r/'src/components/Button.tsx').write_text('export const Button=()=>null');(r/'src/pages/A.tsx').write_text("import {Button} from '@/components/Button'; export const A=()=> <Button/>")
   idx=RepositoryIndexer(r).scan();self.assertIn('src/components/Button.tsx',idx['nodes']['src/pages/A.tsx']['imports'])
 def test_barrel_reexport(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src/components').mkdir(parents=True);(r/'src/components/Button.tsx').write_text('export const Button=()=>null');(r/'src/components/index.ts').write_text("export {Button} from './Button'")
   idx=RepositoryIndexer(r).scan();self.assertIn('src/components/Button.tsx',idx['nodes']['src/components/index.ts']['reexports'])
