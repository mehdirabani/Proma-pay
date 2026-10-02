import unittest,tempfile
from pathlib import Path
from trust.registry import validate_parser,validate_adapter
class T(unittest.TestCase):
 def test_parser(self):self.assertEqual(validate_parser('lighthouse-json')[1],'PASS')
 def test_unknown(self):self.assertEqual(validate_parser('x')[1],'UNTRUSTED_PARSER')
 def test_adapter_tamper(self):
  with tempfile.TemporaryDirectory() as d:
   p=Path(d)/'x.py';p.write_text('bad');self.assertFalse(validate_adapter('cli_adapter',p)[0])
