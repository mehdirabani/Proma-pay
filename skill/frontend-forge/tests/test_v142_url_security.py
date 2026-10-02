import unittest,socket
from security.url_policy import validate_url,URLPolicyError
def fake_private(*a,**k):return [(socket.AF_INET,socket.SOCK_STREAM,6,"",("127.0.0.1",80))]
def fake_public(*a,**k):return [(socket.AF_INET,socket.SOCK_STREAM,6,"",("93.184.216.34",80))]
class TestURLPolicy(unittest.TestCase):
    def test_bad_schemes(self):
        for u in ["file:///etc/passwd","javascript:alert(1)","data:text/plain,x","ftp://x"]:
            with self.subTest(u=u),self.assertRaises(URLPolicyError):validate_url(u,resolver=fake_public)
    def test_private_ssrf(self):
        with self.assertRaises(URLPolicyError):validate_url("http://example.test",resolver=fake_private)
    def test_public_url(self):
        self.assertEqual(validate_url("https://example.test?a=1&b=2",resolver=fake_public)["status"],"PASS")
    def test_redirect_revalidation_equivalent(self):
        with self.assertRaises(URLPolicyError):validate_url("https://redirected.test",resolver=fake_private)
