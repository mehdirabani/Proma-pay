# WAF and ModSecurity audit

| Check | Status | Notes |
|---|---|---|
| ModSecurity enabled state | BLOCKED | Hosting configuration unavailable. |
| Triggered rule ID | BLOCKED | Audit log unavailable. |
| False-positive reproduction | BLOCKED | Affected IP and exact timestamp unavailable. |
| Global WAF disablement | PASSED | No source or deployment change disables ModSecurity/WAF. |
| Broad IP allowlist | PASSED | None added. |
| Controlled application 429 | PASSED | Locked login attempts now return HTTP 429 with `Retry-After`; they do not silently drop TCP. |

Required evidence:

```bash
grep -n -B 20 -A 40 'AFFECTED_IP' /usr/local/apache/logs/modsec_audit.log
grep -n -B 20 -A 40 'AFFECTED_IP' /var/log/modsec_audit.log
grep -R 'ModSecurity: Access denied' /usr/local/lsws/logs /var/log/httpd /var/log/apache2
```

The final rule exception, if needed, must be limited by rule ID, route and validated request shape. Stable release remains BLOCKED until that evidence exists.
