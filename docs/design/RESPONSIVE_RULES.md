# قواعد Responsive و RTL

آزمون‌های اجباری: 360، 375، 390، 430، 768، 1024، 1280، 1440 و 1920 پیکسل.

- Mobile: یک ستون، control تمام‌عرض، actionهای اصلی قابل دسترس و sidebar به drawer.
- Tablet: gridهای مالی 2 ستونه و tableها داخل wrapper افقی قابل پیش‌بینی.
- Desktop: sidebar پایدار، محتوای `minmax(0, 1fr)` و بدون ستون خالی.
- RTL: استفاده از logical propertyها (`inline`, `block`) برای padding/margin/direction؛ اعداد و شناسه‌ها در موارد لازم `dir=ltr`.
- Print: A4 مستقل از app shell، با `break-inside: avoid` برای امضا، ضامن و rowهای مهم.
- Motion: کاهش حرکت با `prefers-reduced-motion` و عدم وابستگی state به animation.
