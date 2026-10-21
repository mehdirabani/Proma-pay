# Production Timeout Findings — 2026-07-23

## Evidence collected

- `proma-mobile.ir` resolved to IPv4 `185.78.22.44`.
- No AAAA record was returned by the A/AAAA lookup.
- TCP connection to `185.78.22.44:443` failed from this environment.
- HTTPS `HEAD` requests to all of the following independently timed out:
  - `/`
  - `/index.php?route=dashboard`
  - `/index.php?route=plugin%2Faccounting%2Fledger%2Fpost`
- The in-app browser navigation to `ledger/post` also timed out before an HTTP response.

## Interpretation

This is evidence of a host/network reachability failure from the test vantage point, not evidence of an application-rendered error and not proof that the client IP was blocked. Because unrelated routes also time out and TCP/HTTPS does not complete, the request cannot be traced inside PHP from this environment.

Current status: **FAILED** for production availability check; root cause classification remains **BLOCKED**.

## Code inspection result

- The Accounting JavaScript contains no `fetch`, `XMLHttpRequest`, `setInterval` or unbounded polling.
- `LedgerService::post` performs a bounded transaction with idempotency, account row lock, one ledger insert, one balance update and an audit insert.
- Outbox processing is guarded to worker/CLI context; normal page requests should not drain batches.

These findings reduce the probability of a browser request storm, but cannot exclude a database lock, PHP-FPM saturation, WAF rule or host-level resource throttle without server logs.

## Required hosting evidence

Provide timestamps in Asia/Tehran for one reproduced incident and the matching excerpts from:

1. web-server access/error and upstream timeout logs;
2. PHP-FPM/LSAPI slow log and worker counts;
3. MySQL processlist, lock waits and slow query log;
4. ModSecurity/WAF/firewall/fail2ban logs showing any rule ID and source IP;
5. hosting resource graphs (CPU, RAM, entry processes, I/O, connections).

Until those records are available, changing firewall rules, whitelisting the IP, or claiming the plugin caused the block would be unsupported.
