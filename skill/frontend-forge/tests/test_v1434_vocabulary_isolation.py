import unittest,json,re
from pathlib import Path
class Vocab(unittest.TestCase):
 def test_holdout_vocab_not_in_production_retrieval(self):
  root=Path(__file__).resolve().parents[1];hold=json.loads((root/'benchmarks/generalization/holdout_vocab.json').read_text())['reserved_for_evaluator_only'];prod=' '.join(p.read_text(errors='ignore').lower() for p in (root/'retrieval').glob('*.py'))
  leaked=[x for x in hold if x.lower() in prod];self.assertEqual(leaked,[])
