import unittest,tempfile
from pathlib import Path
from runtime_py.atomic_ops import AtomicFileTransaction
from regression.baseline_store import BaselineStore
class AtomicBaselineTests(unittest.TestCase):
    def test_atomic_commit(self):
        with tempfile.TemporaryDirectory() as d:
            with AtomicFileTransaction(d) as t:t.write("a.txt","x");t.commit()
            self.assertEqual(Path(d,"a.txt").read_text(),"x")
    def test_atomic_rollback_on_exception(self):
        with tempfile.TemporaryDirectory() as d:
            p=Path(d,"a.txt");p.write_text("old")
            try:
                with AtomicFileTransaction(d) as t:t.write("a.txt","new");raise ValueError("boom")
            except ValueError:pass
            self.assertEqual(p.read_text(),"old")
    def test_atomic_created_file_removed_on_rollback(self):
        with tempfile.TemporaryDirectory() as d:
            with AtomicFileTransaction(d) as t:t.write("new.txt","x")
            self.assertFalse(Path(d,"new.txt").exists())
    def test_idempotent_same_write(self):
        with tempfile.TemporaryDirectory() as d:
            for _ in range(2):
                with AtomicFileTransaction(d) as t:t.write("a.txt","same");t.commit()
            self.assertEqual(Path(d,"a.txt").read_text(),"same")
    def test_baseline_versions(self):
        with tempfile.TemporaryDirectory() as d:
            b=BaselineStore(d);b.put("perf",{"x":1});b.put("perf",{"x":2});self.assertEqual(b.latest("perf")["version"],2)
