import unittest
from pathlib import Path
class Anti(unittest.TestCase):
 def test_production_retrieval_does_not_reference_sealed_truth(self):
  root=Path(__file__).resolve().parents[1];text=''.join(p.read_text(errors='ignore') for p in (root/'retrieval').glob('*.py'))
  self.assertNotIn('sealed_ground_truth',text);self.assertNotIn('holdout-product-en',text)
