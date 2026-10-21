# رگرسیون بصری فرم V1.3.9

ماتریس تصویر برای login، فرم مشتری، قرارداد، پرداخت، لغو، تنظیمات، فایل و افزونه حسابداری:

- normal: PASSED در کارت قرارداد دسکتاپ؛ پس‌زمینه `rgb(247,247,250)` و مرز شفاف.
- hover: NOT EXECUTED
- focus: PASSED در کارت قرارداد دسکتاپ؛ accent داخلی بنفش و shadow ملایم.
- invalid: NOT EXECUTED
- disabled/readonly: NOT EXECUTED
- mobile: PASSED در 390x844 بدون horizontal overflow.
- dark mode: BLOCKED؛ mutation تم در sandbox مرورگر مجاز نبود و کنترل تم زنده در مسیر QA دیده نشد.

هیچ موردی در این فایل PASSED علامت نمی‌خورد مگر با screenshot یا computed-style واقعی.
