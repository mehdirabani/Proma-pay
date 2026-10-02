import unittest,tempfile,json
from pathlib import Path
from repository_intelligence.tsconfig_resolver import ProjectResolver
class PackageResolution(unittest.TestCase):
 def test_package_imports(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'src/utils').mkdir(parents=True);(r/'src/utils/x.ts').write_text('export const x=1');(r/'package.json').write_text(json.dumps({'imports':{'#utils/*':'./src/utils/*'}}));q=ProjectResolver(r);self.assertEqual(q.resolve(r/'src/a.ts','#utils/x'),'src/utils/x.ts')
 def test_workspace_report_partial(self):
  with tempfile.TemporaryDirectory() as d:
   r=Path(d);(r/'package.json').write_text(json.dumps({'workspaces':['packages/*']}));q=ProjectResolver(r);self.assertEqual(q.workspaces,['packages/*']);self.assertIn(q.capability_report()['workspaces'],{'PARTIAL','SUPPORTED'})
