# Proma Accounting 1.2.7 — Test Environment

تاریخ: 2026-07-26

Baseline مورد توافق: افزونهٔ `1.2.6`؛ هدف این تغییرات: `1.2.7`.

وضعیت محیط فعلی برای release gate کامل: **BLOCKED**.

- Core checkout فعلی: `1.4.5` (branch `codex/release-v1.4.5`، commit `ed9ef29`).
- Plugin API: `1.0`.
- staging MySQL/MariaDB مستقل و registry نصب production در دسترس نیست.
- PHP CLI و تست‌های integration/browser/worker در این turn اجرا نشده‌اند: **NOT EXECUTED**.
- هیچ تست destructive یا load روی production انجام نمی‌شود.

نسخهٔ پایدار `1.2.7` فقط بعد از اجرای migration، authorization، money، salary idempotency، performance و regression gate بسته‌بندی خواهد شد.
