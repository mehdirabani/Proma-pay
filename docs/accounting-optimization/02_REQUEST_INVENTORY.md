# Request Inventory

## Browser

`plugins/PromaAccounting/assets/js/accounting.js` contains no dynamic network request, interval or polling loop in static inspection. The only timers are UI focus/transition helpers. Browser multi-tab, hidden-tab and network waterfall tests: **NOT EXECUTED**.

## Server

Outbox processing call sites exist in contract, payment, installment, receipt, payment-group and repair flows. They are now protected by `SystemOutbox::workerContext()`; normal web requests enqueue but should not drain. The intended drain route is `cron/outbox` with the configured cron token and a batch limit capped at 50.

No evidence currently proves that the production cron is configured, non-overlapping or fast enough. Cron overlap, PHP-FPM saturation, DB lock waits, WAF and firewall behavior: **BLOCKED**.

## External requests

No external HTTP request was found in the Accounting JavaScript. A complete PHP/provider inventory still requires Core-wide inspection and staging execution.
