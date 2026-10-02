import unittest,tempfile
from pathlib import Path
from debugging.hypothesis_engine import build_hypotheses
from debugging.root_cause_engine import determine_root_cause
from debugging.evidence import create_evidence
from engineering_intelligence.target_safety import assess_target
from engineering_intelligence.target_evidence import create_target_evidence
class T(unittest.TestCase):
 def test_root_cause_zero_evidence_unverified(self):
  r=determine_root_cause(build_hypotheses('mobile overflow min-width'),[]);self.assertEqual(r['status'],'ROOT_CAUSE_UNVERIFIED')
 def test_irrelevant_evidence_not_confirm(self):
  e=create_evidence('RUNTIME','db','db.log','database timeout','database','r1',1,workspace='w',task_id='t')
  r=determine_root_cause(build_hypotheses('mobile overflow min-width'),[e]);self.assertNotEqual(r['status'],'ROOT_CAUSE_CONFIRMED')
 def test_fake_boolean_target_evidence_rejected(self):
  r=assess_target({'path':'Wrong.tsx','confidence':.99},task_evidence=True,dependency_evidence=True);self.assertEqual(r['state'],'INVALID_TARGET_EVIDENCE');self.assertFalse(r['allow_edit'])
 def test_candidate_binding(self):
  e=create_target_evidence('DEPENDENCY_GRAPH','A.tsx','graph','consumer','r1','/w','t','A consumed by route')
  r=assess_target({'path':'B.tsx','confidence':.99},target_evidence=[e],revision='r1',workspace='/w',task_id='t');self.assertEqual(r['state'],'INVALID_TARGET_EVIDENCE')
 def test_two_typed_independent_evidence_confirm(self):
  es=[create_target_evidence('STATIC_ANALYSIS','A.tsx','static','task-match','r1','/w','t','matching behavior'),create_target_evidence('DEPENDENCY_GRAPH','A.tsx','graph','consumer','r1','/w','t','route consumer')]
  r=assess_target({'path':'A.tsx','confidence':.9},target_evidence=es,revision='r1',workspace='/w',task_id='t');self.assertEqual(r['state'],'TARGET_CONFIRMED')
 def test_root_cause_evidence_bound_to_target(self):
  e=create_evidence('STATIC_ANALYSIS','static','B.css','min-width:600px overflow','B.css','r1',1,workspace='/w',task_id='t')
  r=determine_root_cause(build_hypotheses('mobile overflow min-width'),[e],expected_target='A.css',revision='r1',workspace='/w',task_id='t');self.assertNotEqual(r['status'],'ROOT_CAUSE_CONFIRMED')

