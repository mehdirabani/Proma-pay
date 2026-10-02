import unittest,tempfile
from pathlib import Path
from implementation.patch_engine import targeted_replace,PatchError,syntax_sanity
from implementation.diff_guard import inspect_diff
from planning.change_planner import change_budget
from engineering_intelligence.task_analyzer import analyze_task
class T(unittest.TestCase):
 def test_targeted_patch(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'a.css').write_text('.x { padding: 12px; }')
   c=targeted_replace(d,'a.css','12px','16px');self.assertIn('16px',Path(d,'a.css').read_text());self.assertEqual(c['operation'],'MODIFY')
 def test_ambiguous_replace_fails(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'a').write_text('x x')
   with self.assertRaises(PatchError):targeted_replace(d,'a','x','y')
 def test_unrelated_and_antipattern(self):
  ch=[{'path':'b.ts','before':'const x=1','after':'const x:any=1','lines_changed':1}]
  r=inspect_diff(ch,change_budget(analyze_task('Change padding')),['a.ts']);self.assertEqual(r['status'],'UNRELATED_CHANGE_DETECTED');self.assertTrue(r['anti_pattern_flags'])
 def test_syntax_sanity(self):self.assertTrue(syntax_sanity('function x(){return (1)}'));self.assertFalse(syntax_sanity('function x(){'))
