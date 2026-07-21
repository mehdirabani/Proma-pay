# PHP and web-server capacity audit

| Item | Status | Evidence |
|---|---|---|
| Web server observed after recovery | PASSED | Response identified nginx. |
| PHP version in local QA | PASSED | PHP 8.2.12. |
| Production PHP handler | BLOCKED | LiteSpeed LSAPI/PHP-FPM configuration not supplied. |
| Worker/Entry Process exhaustion | BLOCKED | Hosting resource graphs and worker logs unavailable. |
| Memory exhaustion | BLOCKED | Production PHP fatal/hosting logs unavailable. |
| Connection exhaustion | BLOCKED | Web-server and database connection counters unavailable. |
| Request memory/timing telemetry | PASSED | RC records duration, peak memory and PID when available. |

Collect `Max Connections`, LSAPI children/PHP-FPM pool settings, Entry Processes, NPROC, physical memory, I/O and throttling events for the incident window. Increasing worker counts is not approved without first proving capacity and eliminating slow/blocked work.
