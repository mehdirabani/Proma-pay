# Proma Pay — Performance Constitution

Version: 1.0

Status: Master Specification

Priority: CRITICAL

---

# معرفی

این فایل قانون اساسی Performance پروژه Proma Pay است.

هدف این سند تضمین عملکرد سریع، پایدار و مقیاس‌پذیر سیستم در تمامی بخش‌ها است.

Performance یک قابلیت جانبی نیست؛ بلکه بخشی از کیفیت هر Feature است.

تمام توسعه‌های آینده باید مطابق این سند انجام شوند.

---

# Constitution

## اصل اول

عملکرد صحیح مهم‌تر از امکانات بیشتر است.

اگر یک قابلیت باعث افت محسوس Performance شود، باید قبل از Merge بازطراحی شود.

---

## اصل دوم

هیچ Query نباید بیش از داده موردنیاز را دریافت کند.

استفاده از:

SELECT *

کاملاً ممنوع است.

---

## اصل سوم

تمام لیست‌ها باید Pagination داشته باشند.

مهم نیست تعداد رکورد فعلی چقدر است.

Pagination همیشه فعال باشد.

---

## اصل چهارم

هیچ صفحه‌ای نباید بیش از یک Query مشابه اجرا کند.

Duplicate Query ممنوع است.

---

## اصل پنجم

تمام Queryهای پرتکرار باید Index مناسب داشته باشند.

---

## اصل ششم

N+1 Query ممنوع است.

همیشه قبل از Merge بررسی شود.

---

## اصل هفتم

هیچ صفحه‌ای نباید تمام اطلاعات سیستم را Load کند.

تمام اطلاعات باید Lazy Load شوند.

---

## اصل هشتم

تمام تصاویر باید Optimize شوند.

---

## اصل نهم

تمام Assetها باید Minify شوند.

---

## اصل دهم

تمام عملیات سنگین باید Async باشند.

---

# Performance Targets

صفحه Dashboard

حداکثر:

1.5 ثانیه

---

صفحه قراردادها

حداکثر:

2 ثانیه

---

صفحه مشتریان

حداکثر:

2 ثانیه

---

جزئیات قرارداد

حداکثر:

1.5 ثانیه

---

گفتگو

حداکثر:

1 ثانیه

---

جستجو

کمتر از:

500ms

---

# Database Performance

تمام Queryها باید:

Prepared Statement

داشته باشند.

---

Queryهای Join باید فقط ستون‌های موردنیاز را دریافت کنند.

---

ORDER BY روی ستون‌های Index شده انجام شود.

---

LIMIT همیشه استفاده شود.

---

# Caching

اطلاعاتی که تغییرات کمی دارند باید Cache شوند.

نمونه:

تنظیمات

مدال‌ها

سطوح دسترسی

لیست بانک‌ها

لیست استان‌ها

---

Cache باید قابلیت Invalid شدن داشته باشد.

---

# File System

هیچ فایل موقتی نباید بدون زمان انقضا باقی بماند.

---

رسیدهای پرداخت

↓

حذف خودکار

---

تصاویر چت

↓

حذف خودکار

---

Temporary Upload

↓

حذف خودکار

---

# Image Optimization

تمام تصاویر قبل از ذخیره:

Resize

Compress

Optimize

شوند.

---

Thumbnailها تولید شوند.

---

# Frontend Performance

تمام فایل‌های CSS و JS باید:

Minify

Bundle

Versioning

داشته باشند.

---

Assetها Cache شوند.

---

# Loading Strategy

هیچ Spinner ساده‌ای استفاده نشود.

Skeleton Loading استفاده شود.

---

Infinite Scroll فقط در بخش‌هایی استفاده شود که تجربه کاربری را بهبود دهد.

---

# JavaScript

از اجرای کدهای تکراری جلوگیری شود.

Event Delegation در صورت امکان استفاده شود.

---

# Chat

پیام‌های قدیمی به صورت Lazy Load نمایش داده شوند.

---

Real Time فقط روی بخش‌های لازم فعال باشد.

---

# Notification

Polling ممنوع است مگر در شرایط خاص.

ترجیح:

WebSocket

---

# Calendar

رویدادها به صورت ماهانه Load شوند.

نه کل سال.

---

# Reports

گزارش‌های سنگین باید Queue شوند.

---

# Backup

Backup باید در Background اجرا شود.

---

# Update

Update باید مرحله‌ای انجام شود.

هیچ Update نباید باعث Down شدن کل سیستم شود.

---

# Memory

هیچ Loop نباید کل اطلاعات دیتابیس را داخل RAM بارگذاری کند.

Chunk Processing استفاده شود.

---

# Logging

Logهای قدیمی Archive شوند.

---

# Implementation Rules

قبل از Merge هر Feature:

□ تعداد Queryها بررسی شود.

□ Duplicate Query بررسی شود.

□ Indexها بررسی شوند.

□ تصاویر بررسی شوند.

□ Assetها بررسی شوند.

□ Cache بررسی شود.

□ Memory بررسی شود.

□ JavaScript بررسی شود.

□ Responsive بررسی شود.

□ WebSocket بررسی شود.

---

# Review Checklist

قبل از تأیید نهایی:

□ Queryها بهینه هستند.

□ Pagination وجود دارد.

□ N+1 وجود ندارد.

□ SELECT * وجود ندارد.

□ Assetها Minify شده‌اند.

□ تصاویر Optimize شده‌اند.

□ Cache رعایت شده است.

□ عملیات سنگین Async شده‌اند.

□ زمان بارگذاری در محدوده هدف است.

□ Lighthouse افت نکرده است.

---

# Definition of Done

یک Feature زمانی کامل است که:

✔ سریع باشد.

✔ مقیاس‌پذیر باشد.

✔ روی دیتابیس بزرگ نیز عملکرد مناسب داشته باشد.

✔ باعث افزایش غیرضروری Queryها نشود.

✔ Memory Leak نداشته باشد.

✔ Cache مناسب داشته باشد.

✔ تصاویر بهینه شده باشند.

✔ تست Performance را با موفقیت پشت سر گذاشته باشد.

---

# Future Considerations

در نسخه‌های آینده سیستم باید آماده موارد زیر باشد:

- Redis Cache
- Queue Worker
- WebSocket Server
- CDN
- Image CDN
- Horizontal Scaling
- Multi-Server Deployment
- Background Workers
- Distributed Cache
- Read Replica Database

معماری فعلی نباید مانع اضافه شدن این قابلیت‌ها شود.

---

# پایان فایل