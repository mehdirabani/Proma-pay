import unittest,tempfile
from pathlib import Path
from artifacts.artifact_store import register_artifact
from security.path_policy import PathPolicyError

class V141PathTests(unittest.TestCase):
    def test_artifact_workspace_escape_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            outside=Path(d).parent/'outside-artifact.txt';outside.write_text('x')
            try:
                with self.assertRaises(PathPolicyError):register_artifact(outside,'x','test',workspace=d)
            finally:outside.unlink(missing_ok=True)
    def test_artifact_inside_workspace_allowed(self):
        with tempfile.TemporaryDirectory() as d:
            p=Path(d,'a.txt');p.write_text('x');r=register_artifact(p,'x','test',workspace=d);self.assertEqual(r['hash'],__import__('hashlib').sha256(b'x').hexdigest())
