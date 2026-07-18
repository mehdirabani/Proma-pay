# دسترس‌پذیری کنترل‌های فرم

- فیلد focused با تغییر رنگ زمینه و accent داخلی قابل تشخیص است؛ `outline: none` بدون جایگزین وجود ندارد.
- label برای همه فیلدهای ضروری حفظ شده و required marker توسط JavaScript به label افزوده می‌شود.
- placeholder ثانویه و کم‌رنگ‌تر از متن واردشده است.
- checkbox/radio با keyboard focus و label لمسی مستقل کار می‌کنند.
- دکمه نمایش رمز باید `type="button"` و نام قابل دسترس داشته باشد.
- خطا فقط رنگی نیست؛ پیام متنی زیر فیلد باید باقی بماند.
- وضعیت آزمون screen-reader کامل: NOT EXECUTED (نیازمند ابزار بومی NVDA/VoiceOver خارج از محیط QA محلی).
