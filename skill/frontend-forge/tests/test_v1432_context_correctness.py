import unittest,tempfile
from pathlib import Path
from token_intelligence.dedup import deduplicate
from token_intelligence.context_selector import select
class ContextCorrectness(unittest.TestCase):
    def test_code_plus_minus_not_dedup(self):
        x=[{'path':'a.ts','text':'export const total = price + tax'},{'path':'b.ts','text':'export const total = price - tax'}]
        self.assertEqual(len(deduplicate(x)['kept']),2)
    def test_boolean_not_dedup(self):
        x=[{'path':'a.ts','text':'export const enabled = true'},{'path':'b.ts','text':'export const enabled = false'}]
        self.assertEqual(len(deduplicate(x)['kept']),2)
    def test_huge_p0_is_bounded(self):
        c=[{'path':'Huge.ts','text':'export const x=1;\n'*10000,'targets':['Huge.ts'],'symbols':['x']}]
        r=select(c,'change Huge.ts',100);self.assertEqual(r['status'],'PASS');self.assertLessEqual(r['estimated_context_tokens'],100);self.assertEqual(r['loaded'][0]['representation'],'bounded-structure')
    def test_no_required_capacity_reports_exceeded(self):
        c=[{'path':'a.ts','text':'x','targets':['a.ts']}]
        r=select(c,'a.ts',0);self.assertEqual(r['status'],'CONTEXT_BUDGET_EXCEEDED')
