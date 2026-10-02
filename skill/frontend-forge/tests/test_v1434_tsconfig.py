import unittest,tempfile,json
from pathlib import Path
from repository_intelligence.tsconfig_resolver import ProjectResolver,ConfigCycle
class TSC(unittest.TestCase):
 def test_extends_paths(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'config').mkdir();(r/'src/lib').mkdir(parents=True);(r/'src/lib/x.ts').write_text('export const x=1')
   (r/'config/base.json').write_text(json.dumps({'compilerOptions':{'baseUrl':'..','paths':{'@lib/*':['src/lib/*']}}}))
   (r/'tsconfig.json').write_text(json.dumps({'extends':'./config/base.json'}));q=ProjectResolver(r);self.assertEqual(q.resolve(r/'src/a.ts','@lib/x'),'src/lib/x.ts')
 def test_cycle_rejected(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'a.json').write_text(json.dumps({'extends':'./b.json'}));(r/'b.json').write_text(json.dumps({'extends':'./a.json'}));(r/'tsconfig.json').write_text(json.dumps({'extends':'./a.json'}))
   with self.assertRaises(ConfigCycle):ProjectResolver(r)
