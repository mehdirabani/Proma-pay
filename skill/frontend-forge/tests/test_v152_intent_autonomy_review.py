import unittest
from engineering_intelligence.task_analyzer_v3 import analyze_task_v3
from engineering_intelligence.autonomy import choose_autonomy
from review.self_review import self_review
class TestIntentAutonomyReview(unittest.TestCase):
 def test_add_to_fix_is_repair(self):
  m=analyze_task_v3("Add overflow-hidden to fix the mobile overflow");self.assertEqual(m["operation"],"REPAIR");self.assertEqual(m["edit_intent"],"ADD")
 def test_persian_add_to_fix_is_repair(self):
  m=analyze_task_v3("برای رفع مشکل یک کلاس اضافه کن");self.assertEqual(m["operation"],"REPAIR")
 def test_high_impact_overrides_low_task(self):
  tm={"operation":"REPAIR","risk":"LOW"};ts={"state":"TARGET_CONFIRMED"};rc={"status":"ROOT_CAUSE_CONFIRMED"}
  r=choose_autonomy(tm,ts,{"risk":"HIGH","centrality":.95,"public_api_impact":False},root_cause=rc,code_intelligence_mode="FULL_COMPILER",evidence_quality=.9)
  self.assertEqual(r["level"],"L2_PLAN")
 def test_missing_acceptance_not_pass(self):
  tm={"operation":"REPAIR","acceptance_criteria":["responsive_behavior_correct"]};rc={"status":"ROOT_CAUSE_CONFIRMED"}
  r=self_review(tm,rc,{"unrelated_changes":[],"anti_pattern_flags":[]},[{"status":"PASS"}],target_safety={"state":"TARGET_CONFIRMED"},acceptance_results=None)
  self.assertNotEqual(r["status"],"PASS");self.assertEqual(r["acceptance_coverage"],0)
