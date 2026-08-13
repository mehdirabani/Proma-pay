# Bug Register

| ID | Severity | وضعیت | شواهد | اصلاح |
|---|---|---|---|---|
| ACC-REQUEST-STORM-001 | High | FIX IMPLEMENTED; RETEST NOT EXECUTED | `processPending` در مسیرهای عادی با batchهای 10–50 | guard worker + cron توکن‌دار و batch حداکثر 50 |
| ACC-COMMISSION-AUTO-001 | High | BLOCKED | event/outbox/listener زنجیره‌ای است؛ DB واقعی در دسترس نیست | نیازمند staging reproduction و بررسی timing/rule |
| ACC-WAF-001 | High | BLOCKED | فقط screenshot از timeout موجود است | بررسی access/WAF/firewall logs لازم است |
| ACC-POLLING-001 | Medium | PASSED (static only) | polling/fetch در accounting.js پیدا نشد | تست browser چندتب هنوز لازم است |
