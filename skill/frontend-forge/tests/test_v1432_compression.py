import unittest
from token_intelligence.compression_engine import compress_typescript_detailed,compress_logs_detailed
class Compression(unittest.TestCase):
    def test_ts_finding_count_preserved(self):
        raw="a.ts(1,1): error TS2322: bad\nb.ts(2,1): error TS2322: bad\nc.ts(1,1): error TS7006: implicit"
        r=compress_typescript_detailed(raw);self.assertEqual(r['source_findings'],3);self.assertEqual(r['preserved_findings'],3)
    def test_unknown_fallback_raw(self):
        r=compress_typescript_detailed('strange compiler failure xyz');self.assertEqual(r['status'],'FALLBACK_RAW');self.assertIn('strange',r['raw_relevant'])
    def test_log_fallback(self):self.assertEqual(compress_logs_detailed('nothing structured')['status'],'FALLBACK_RAW')
