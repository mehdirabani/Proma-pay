import unittest,json
from benchmarks.agent_usage import UnavailableAgentUsageAdapter
class T(unittest.TestCase):
 def test_provider_usage_not_synthesized(self):
  a=UnavailableAgentUsageAdapter();r=a.execute('x','y','z');self.assertEqual(r['status'],'PROVIDER_USAGE_UNAVAILABLE');self.assertIsNone(a.usage(r)['input_tokens'])
