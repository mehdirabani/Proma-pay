import unittest,tempfile
from pathlib import Path
from transactions.durable_files import DurableFileTransaction,DurableTransactionError
class TestDurableTx(unittest.TestCase):
    def test_crash_recovery_fully_old(self):
        with tempfile.TemporaryDirectory() as d:
            for i in range(5):Path(d,f"f{i}.txt").write_text(f"old{i}")
            tx=DurableFileTransaction(d);tx.begin([{"path":f"f{i}.txt","data":f"new{i}"} for i in range(5)])
            with self.assertRaises(DurableTransactionError):tx.commit(crash_after=2)
            DurableFileTransaction.recover_all(d)
            self.assertEqual([Path(d,f"f{i}.txt").read_text() for i in range(5)],[f"old{i}" for i in range(5)])
    def test_commit_fully_new(self):
        with tempfile.TemporaryDirectory() as d:
            for i in range(3):Path(d,f"f{i}").write_text("old")
            tx=DurableFileTransaction(d);tx.begin([{"path":f"f{i}","data":"new"} for i in range(3)]);tx.commit()
            self.assertEqual([Path(d,f"f{i}").read_text() for i in range(3)],["new"]*3)
