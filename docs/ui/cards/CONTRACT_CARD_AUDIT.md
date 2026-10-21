# ممیزی کارت قرارداد

## ریشه تداخل قبلی

انتخاب‌گر در نسخه‌های قبلی از جریان normal layout جدا می‌شد. در V1.3.9 header به سه منطقه grid تقسیم شده است:

1. identity: آواتار، نام مشتری، مبلغ.
2. controls: checkbox انتخاب و عملیات سریع.
3. meta: شماره قرارداد و وضعیت.

`.proma-card-select` اکنون `position: static` دارد، area رزروشده دارد و حداقل target لمس 44px است. عملیات چاپ، دفترچه، نمودار، timeline و لغو در کارت icon-only، دارای `title` و `aria-label` هستند.

نتیجه تست geometry دسکتاپ/موبایل: NOT EXECUTED تا ثبت QA مرورگر.
