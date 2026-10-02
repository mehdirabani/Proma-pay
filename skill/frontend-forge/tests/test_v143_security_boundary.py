import unittest,tempfile,hashlib
from provenance.authority import issue_execution_receipt,UnauthorizedProvenanceIssuer
from provenance.execution_broker import ExecutionBroker
from provenance.ipc_protocol import make_request
from provenance.keys.file_key_provider import FileKeyProvider
from security.sandbox_policy import SandboxPolicy
class T(unittest.TestCase):
 def test_direct_sign_blocked(self):
  with self.assertRaises(UnauthorizedProvenanceIssuer):issue_execution_receipt('x')
 def test_key_permission(self):
  with tempfile.TemporaryDirectory() as d:
   p=FileKeyProvider(d);self.assertEqual(oct((p.root/f'{p.key_id()}.pem').stat().st_mode&0o777),'0o600')
 def test_unauthorized_ipc(self):
  with tempfile.TemporaryDirectory() as d:
   b=ExecutionBroker(d)
   try:
    b.signer.conn.send(make_request(b'bad','intruder',{'execution_id':'x','capability_id':'x'}));r=b.signer.conn.recv();self.assertEqual(r['error'],'ACCESS_DENIED')
   finally:b.close()
 def test_wrong_capability(self):
  with tempfile.TemporaryDirectory() as d:
   b=ExecutionBroker(d)
   try:
    rec={'execution_id':'e','capability_id':'typescript-check','requested_capability_id':'lighthouse-check'};b.signer.conn.send(make_request(b.secret,b.broker_id,rec));r=b.signer.conn.recv();self.assertEqual(r['error'],'CAPABILITY_IDENTITY_MISMATCH')
   finally:b.close()
 def test_duplicate_execution_sign(self):
  with tempfile.TemporaryDirectory() as d:
   b=ExecutionBroker(d)
   try:
    rec={'execution_id':'e','capability_id':'typescript-check','requested_capability_id':'typescript-check'}
    b.signer.conn.send(make_request(b.secret,b.broker_id,rec));self.assertTrue(b.signer.conn.recv()['ok'])
    b.signer.conn.send(make_request(b.secret,b.broker_id,rec));self.assertEqual(b.signer.conn.recv()['error'],'REPLAY_DETECTED')
   finally:b.close()
