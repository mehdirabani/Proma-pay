# Network and DNS audit

Audit date: 2026-07-21.

| Check | Status | Result |
|---|---|---|
| A record | PASSED | `proma-mobile.ir` resolved to `185.78.22.44`. |
| AAAA record | PASSED | No AAAA record was published; there is no invalid advertised IPv6 endpoint. |
| Native IPv6 service path | NOT EXECUTED | No AAAA endpoint exists to test. |
| Authoritative nameservers | PASSED | `irns1.netafraz.com`, `irns2.netafraz.com`. |
| TCP 443 after recovery | PASSED | `Test-NetConnection` succeeded. |
| HTTPS after recovery | PASSED | HTTP 200 with TLS established. |
| Multi-vantage HTTP | PASSED | Five independent Check-Host nodes returned 200. |
| TLS client validation | PASSED | Windows Schannel curl accepted the production certificate. |
| OpenSSL local trust-store validation | BLOCKED | The local OpenSSL bundle reported an unavailable issuer; this did not reproduce in Schannel and needs an external chain audit from the hosting side. |
| HTTP to HTTPS redirect | FAILED | Port 80 returned content instead of a redirect during the probe. Configure the redirect at the hosting/reverse-proxy layer to avoid application redirect loops. |

Multi-vantage evidence: https://check-host.net/check-report/4505482fk743

No DNS change is justified by the available evidence. In particular, disabling IPv6 is not a remediation because no AAAA record is currently advertised.
