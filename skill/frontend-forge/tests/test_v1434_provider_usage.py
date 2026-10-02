import unittest
from benchmarks.agent_usage import UnavailableAgentUsageAdapter
class U(unittest.TestCase):
 def test_no_synthetic_usage(self):
  a=UnavailableAgentUsageAdapter();r=a.execute('x','y','baseline');u=a.usage(r);self.assertEqual(u['status'],'PROVIDER_USAGE_UNAVAILABLE');self.assertIsNone(u['input_tokens'])
