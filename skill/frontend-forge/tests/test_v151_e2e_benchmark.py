import unittest
from benchmarks.engineering_v151.run_benchmark import run
class T(unittest.TestCase):
    def test_e2e_fixture_pipeline(self):
        r=run()
        self.assertGreaterEqual(r['tasks'],50)
        self.assertEqual(r['solved'],r['tasks'])
        self.assertEqual(r['measurement'],'VERIFIED_E2E_FIXTURE')
