import unittest
from token_intelligence.context_budget import allocate
from token_intelligence.task_complexity import classify,graph_depth
class T(unittest.TestCase):
 def test_reserve_never_zero(self):self.assertGreater(allocate(16000,'TRIVIAL')['reserve_tokens'],0)
 def test_window_not_filled(self):self.assertLess(allocate(16000,'LARGE')['window_utilization'],1)
 def test_complexity(self):self.assertEqual(classify('Change button padding'),'TRIVIAL');self.assertEqual(classify('Migrate architecture'),'LARGE')
 def test_depth_adaptive(self):self.assertLess(graph_depth('TRIVIAL'),graph_depth('LARGE'))
