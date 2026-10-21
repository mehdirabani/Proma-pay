# IP block regression results

| Scenario | Status | Notes |
|---|---|---|
| Normal repeated live checks after recovery | PASSED | Ten requests returned 200. |
| Multi-vantage access | PASSED | Five external nodes returned 200. |
| Locked login returns HTTP response | PASSED | Source and automated test confirm controlled 429 plus `Retry-After`. |
| Normal authenticated Accounting navigation from affected IP | BLOCKED | Affected public IP unavailable. |
| Confirm no CSF/LFD/Fail2ban ban after approved browsing | BLOCKED | Security logs unavailable. |
| Confirm malicious brute force remains blocked | BLOCKED | Requires controlled staging/WAF test. |
| ModSecurity false-positive regression | BLOCKED | Rule ID unavailable. |

Production IP-block regression is not replaceable with a localhost test. Stable gate remains BLOCKED.
