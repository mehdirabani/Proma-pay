# Timeout and suspected IP block incident

## Incident status

| Item | Status | Evidence |
|---|---|---|
| Intermittent no-HTTP-response symptom | PASSED | The first local probe to `/`, login, Accounting dashboard and both health routes returned curl code `000` after about 10 seconds. |
| General client Internet connectivity | PASSED | A control request to `https://example.com` returned HTTP 200 in the same investigation window. |
| Service recovery | PASSED | Later forced-address HTTPS requests and ten repeated `/health/live` requests returned HTTP 200 without a modem restart. |
| Persistent client-IP ban | BLOCKED | The affected public client IP and firewall/WAF logs were not supplied. |
| Exact incident timestamp | BLOCKED | The browser report did not include an exact server-correlatable timestamp. |
| Exact root cause | BLOCKED | No LiteSpeed/Apache, PHP worker, ModSecurity, CSF/LFD, Fail2ban or MySQL incident-window logs are available. |

The reported application version was not trusted. The canonical source version was `1.4.1`; diagnostic development therefore uses `1.4.2-rc.1`.

## Timeline

- Report received: date visible in the workspace context, but exact browser failure time is unavailable.
- 2026-07-21 investigation: all five first-party routes timed out before an HTTP status was received.
- The local TCP 443 probe subsequently succeeded.
- Forced IPv4 HTTPS then returned HTTP 200 with nginx and TLS established.
- Check-Host request `4505482fk743` returned HTTP 200 from Switzerland, Netherlands, Sweden, Turkey and the United States in approximately 0.51 to 0.76 seconds.
- Ten local `/health/live` probes then returned HTTP 200 in approximately 0.15 to 0.21 seconds.

Because `/health/live` bypasses session, database and plugin boot, its failure in the first window means the Accounting controller alone cannot explain that window. The later multi-vantage success also means a global persistent outage was not established.

## Evidence still required

Collect a 15-minute window before and after the exact event time and keep the affected client IP masked in reports:

```bash
grep 'AFFECTED_IP' /usr/local/lsws/logs/access.log /usr/local/lsws/logs/error.log
grep 'AFFECTED_IP' /var/log/httpd/* /var/log/apache2/*
grep 'AFFECTED_IP' /usr/local/apache/logs/modsec_audit.log /var/log/modsec_audit.log
csf -g AFFECTED_IP
grep 'AFFECTED_IP' /var/log/lfd.log /var/log/fail2ban.log /var/log/secure
fail2ban-client status
mysqladmin processlist
```

Also export hosting CPU, Entry Processes, Physical Memory, I/O, NPROC and concurrent connection graphs for the same window.

## Release decision

Stable release: **BLOCKED**. Local source hardening may be published only as an RC until the blocking component and security rule/jail are identified.
