# External HTTP call audit

| Service | Connect timeout | Total timeout | TLS verification | Status |
|---|---:|---:|---|---|
| Zibal | 5 s | 15 s | enabled, HTTPS only | PASSED |
| IPPanel | 5 s | 15 s | enabled, HTTPS only | PASSED |
| OpenRouter | 5 s | 30 s | enabled, HTTPS only | PASSED |
| Proma Zarinpal | 5 s default | 20 s default | enabled, HTTPS only | PASSED |

Each cURL call records only service name, elapsed time, HTTP status and success state. Credentials, payloads and response bodies are excluded.

Provider outage injection: **NOT EXECUTED** against production. Local static timeout assertions are PASSED. No external call exists in the Accounting dashboard path.
