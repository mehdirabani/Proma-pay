# Root cause and remediation

## Classification

Exact infrastructure root cause: **BLOCKED**.

Observed facts support an intermittent failure before HTTP response. The first window affected the dependency-free live endpoint, while later checks recovered and five external vantage points were healthy. Therefore neither “Accounting alone” nor “persistent global outage” is established. A persistent IP ban is also not proven.

## Confirmed application amplifiers

- PHP session lock covered the complete Accounting dashboard request.
- The dashboard performed repeated aggregates, an unbounded ledger total and an unused correlated account query.
- Chat and notification polling used fixed intervals without backoff, hidden-tab pause or overlap prevention.
- External HTTP clients could retain a worker for 30 to 45 seconds.
- Queue execution lacked a dedicated bounded cron entry and overlap lock in the source baseline.
- Request timing did not identify where elapsed time was spent.

These factors are remediated in `1.4.2-rc.1` and Accounting `1.2.5-rc.1` with structured telemetry, query consolidation, session release, widget degradation, adaptive polling, bounded clients and cron locks.

## Remaining blockers

- Exact firewall/WAF/Fail2ban/CSF component and rule ID.
- Incident-window PHP/web-server/hosting resource evidence.
- MySQL lock and slow-query evidence.
- Production or staging load, soak and IP-block regression tests.
- HTTP-to-HTTPS redirect correction at the hosting/proxy layer.

Stable publication remains **BLOCKED**. `tools/availability-gate.php` requires protected production evidence and cannot pass from static source inspection.
