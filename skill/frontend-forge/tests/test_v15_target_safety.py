import unittest
from engineering_intelligence.target_safety import assess_target
from engineering_intelligence.target_evidence import create_target_evidence
from engineering_intelligence.autonomy import choose_autonomy
from engineering_intelligence.task_analyzer import analyze_task
class T(unittest.TestCase):
 def test_wrong_target_confidence_alone_rejected(self):
  r=assess_target({'path':'Wrong.tsx','confidence':.99});self.assertFalse(r['allow_edit']);self.assertEqual(r['state'],'TARGET_AMBIGUOUS')
 def test_boolean_evidence_rejected(self):
  r=assess_target({'path':'A.tsx','confidence':.8},task_evidence=True,dependency_evidence=True);self.assertEqual(r['state'],'INVALID_TARGET_EVIDENCE')
 def test_confirmed_needs_two_typed_sources(self):
  es=[create_target_evidence('STATIC_ANALYSIS','A.tsx','s','match','r','/w','t','task match'),create_target_evidence('DEPENDENCY_GRAPH','A.tsx','g','consumer','r','/w','t','route consumer')]
  r=assess_target({'path':'A.tsx','confidence':.8},target_evidence=es,revision='r',workspace='/w',task_id='t');self.assertEqual(r['state'],'TARGET_CONFIRMED');self.assertTrue(r['allow_edit'])
 def test_autonomy_blocks_ambiguous(self):
  tm=analyze_task('Change padding');a=choose_autonomy(tm,{'state':'TARGET_AMBIGUOUS'});self.assertIn(a['level'],{'L0_ANALYZE_ONLY','L1_SUGGEST'})
