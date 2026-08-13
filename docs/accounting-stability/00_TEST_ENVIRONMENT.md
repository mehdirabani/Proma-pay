# Accounting Stability — Test Environment

تاریخ: 2026-07-21

وضعیت محیط فعلی: **BLOCKED** برای تست integration/production-like.

- workspace: `codex/release-v1.4.1`, Core `1.4.1`، Accounting manifest `1.2.4`
- محیط staging مستقل و dump ماسک‌شدهٔ MySQL/MariaDB در اختیار این اجرا نیست.
- PHP CLI در PATH محیط Codex نیست؛ تست‌های PHP در این turn اجرا نشده‌اند: **NOT EXECUTED**.
- هیچ load test یا query destructive روی production اجرا نشده است.
- برای اجرای worker، cron توکن‌دار `cron/outbox?token=...&limit=25` باید در staging تنظیم شود؛ مقدار توکن در مستندات ثبت نمی‌شود.

پیش‌نیاز release gate: staging جدا با PHP/DB/web-server واقعی، لاگ‌های جدا، دادهٔ synthetic، providerهای fake و ثبت worker/connection limits.
