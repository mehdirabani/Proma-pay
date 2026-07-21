# Timeout and IP Incident Report - V1.4.3-rc.1

## Current classification

Two different failure classes must not be conflated:

1. Application login throttling could previously lock all identifiers sharing one public IP. This application defect is fixed in V1.4.3-rc.1.
2. Browser ERR_CONNECTION_TIMED_OUT with no response from health/live occurs before PHP and cannot be remediated by application code alone.

## Application remediation

- Pure IP scope is telemetry-only and cannot create an application lock.
- Identifier and identifier-plus-IP scopes still rate-limit repeated invalid credentials.
- A successful login clears account scopes and decays the IP telemetry counter.
- System Health exposes whether the current request reached PHP, a masked network prefix, and a request correlation id.
- Administrators can download a privacy-safe diagnostic bundle.

## Stable release blockers

Stable V1.4.3 must not be published until all of the following have production evidence:

- Web-server access and error logs for an affected timestamp.
- ModSecurity audit log and rule id, or explicit proof that no rule blocked the request.
- CSF/LFD, Fail2ban, CDN, reverse-proxy, and hosting firewall deny logs.
- Direct tests of health/live from the affected IP before and after an IP change.
- IPv4, IPv6, DNS, TLS, worker saturation, and database lock checks.
- Repeated load, soak, and IP regression tests.

## Required interpretation

If health/live returns JSON, PHP was reached and the request id can be correlated with telemetry. If the same endpoint times out only from one public IP and produces no web-server access record, the block is upstream of Proma Pay.
