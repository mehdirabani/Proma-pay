# صف و تلاش مجدد

ارسال از عملیات Core جداست. delivery دارای idempotency_key یکتا، backoff محدود، پنج تلاش و وضعیت dead است. خطای ارائه‌دهنده transaction مالی یا امضای commit‌شده را rollback نمی‌کند.
