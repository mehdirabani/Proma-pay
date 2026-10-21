# Proma Pay — Database Design Specification

Version: 1.0

Status: Master Specification

Priority: CRITICAL

---

# معرفی

این فایل مرجع اصلی طراحی دیتابیس پروژه Proma Pay است.

تمامی جداول، روابط، Migrationها، Indexها، کلیدهای خارجی، قوانین حذف داده، Backup و توسعه آینده باید مطابق این سند انجام شوند.

هیچ جدول یا ستونی نباید بدون بررسی این سند ایجاد شود.

---

# هدف

این سند تضمین می‌کند که:

- دیتابیس در آینده قابل توسعه باشد.
- Migrationها باعث از بین رفتن اطلاعات نشوند.
- Queryها سریع باشند.
- ساختار استاندارد باقی بماند.
- از ایجاد جدول‌های تکراری جلوگیری شود.

---

# اصول کلی

تمام جداول باید:

✔ Primary Key داشته باشند.

✔ created_at داشته باشند.

✔ updated_at داشته باشند.

✔ در صورت نیاز deleted_at داشته باشند.

✔ Index مناسب داشته باشند.

✔ UTF8MB4 باشند.

✔ از InnoDB استفاده کنند.

---

# نوع داده‌ها

IDها

BIGINT UNSIGNED

---

مبالغ

DECIMAL(15,2)

هیچ مبلغی نباید FLOAT یا DOUBLE باشد.

---

متن کوتاه

VARCHAR

---

متن بلند

TEXT

---

JSON

فقط زمانی استفاده شود که ساختار متغیر باشد.

---

Boolean

TINYINT(1)

---

تاریخ

DATE

---

زمان

TIME

---

تاریخ و زمان

DATETIME

---

# سیاست کلید اصلی

تمام جدول‌ها:

id BIGINT UNSIGNED AUTO_INCREMENT

---

# سیاست کلید خارجی

Foreign Key فقط زمانی ایجاد شود که:

مزیت آن بیشتر از هزینه آن باشد.

در جداول بسیار پرترافیک می‌توان فقط Index ایجاد کرد.

---

# Index Policy

هر ستونی که:

جستجو می‌شود.

JOIN می‌شود.

Filter می‌شود.

Sort می‌شود.

باید Index داشته باشد.

---

نمونه:

customer_id

contract_id

installment_id

status

created_at

phone

national_code

serial_number

---

# Composite Index

در Queryهای پرتکرار از Composite Index استفاده شود.

مثال:

(customer_id, status)

(contract_id, due_date)

(status, created_at)

---

# ممنوع

Index روی ستون‌هایی که استفاده نمی‌شوند.

---

# Soft Delete

تمام اطلاعات مهم باید Soft Delete شوند.

نمونه:

Customers

Contracts

Users

Products

Guarantees

---

نباید Soft Delete شوند:

Logs

Payments

Installments

Timeline

Notification Logs

---

# Archive Policy

اطلاعات قدیمی حذف نشوند.

در صورت نیاز Archive شوند.

---

# Naming Convention

جدول‌ها:

snake_case

جمع

نمونه:

customers

contracts

payments

notifications

---

ستون‌ها:

snake_case

---

Foreign Key

customer_id

contract_id

user_id

lawyer_id

---

# Unique Rules

باید Unique باشند:

contract_number

national_code

username

email

---

در صورت نیاز:

phone

---

# Money Rules

تمام مبالغ:

ریال

DECIMAL

بدون اعشار منطقی

---

هیچ مبلغی نباید داخل View محاسبه شود.

---

# Settings Table

تمام تنظیمات پروژه داخل یک جدول مرکزی ذخیره شوند.

نمونه:

settings

کلید

مقدار

توضیح

گروه

نوع داده

---

# Files

هیچ فایل آپلودی داخل دیتابیس ذخیره نشود.

فقط Path ذخیره شود.

---

# Attachments

تمام فایل‌ها باید Metadata داشته باشند.

نمونه:

نام فایل

نام اصلی

اندازه

Mime

Uploader

Created At

---

# Chat Images

تصاویر چت:

بعد از زمان تعیین شده حذف شوند.

---

# Payment Receipts

رسیدها:

پس از تأیید یا رد

حذف شوند.

---

# Identity Documents

مدرک جدید:

↓

Pending

↓

Approved

↓

مدرک قبلی حذف

↓

مدرک جدید فعال

---

اگر رد شد:

↓

مدرک جدید حذف

↓

مدرک قبلی باقی بماند.

---

# Notification

Notificationها حذف نشوند.

فقط وضعیت خوانده شده تغییر کند.

---

# Timeline

Timeline فقط Append Only باشد.

ویرایش نشود.

---

# Logs

Logها حذف نشوند.

---

# Migration Policy

Migration باید:

Safe

Rollbackable

Idempotent

باشد.

---

قبل از Migration بررسی کن:

جدول وجود دارد؟

ستون وجود دارد؟

Index وجود دارد؟

---

DROP TABLE

ممنوع

---

TRUNCATE

ممنوع

---

ALTER بدون بررسی

ممنوع

---

# Backup Policy

قبل از Migration:

Backup الزامی است.

---

# Update Policy

Migration نباید اطلاعات کاربر را حذف کند.

---

# Performance Rules

هیچ Query نباید Full Table Scan غیرضروری انجام دهد.

Pagination الزامی است.

LIMIT الزامی است.

Prepared Statement الزامی است.

---

# Query Rules

هیچ Query نباید:

SELECT *

داشته باشد.

فقط ستون‌های موردنیاز انتخاب شوند.

---

# Future Ready

تمام طراحی دیتابیس باید امکان اضافه شدن موارد زیر را داشته باشد:

- چند فروشگاه
- چند شعبه
- چند شرکت
- چند ارز
- چند زبان
- Plugin System
- API
- Mobile App

بدون نیاز به بازطراحی کامل دیتابیس.

---

# Database Modules

دیتابیس باید به صورت منطقی به دامنه‌های زیر تقسیم شود:

Identity

Financial

Legal

Communication

Calendar

Notification

Settings

Reports

Files

Logs

---

# Implementation Rules for Codex

قبل از ایجاد هر جدول:

□ جدول مشابه وجود ندارد.

□ ستون مشابه وجود ندارد.

□ Index بررسی شده است.

□ Foreign Key بررسی شده است.

□ Performance بررسی شده است.

□ Migration ایمن است.

□ Rollback تعریف شده است.

□ مستندات به‌روزرسانی شده‌اند.

---

# Definition of Done

هر تغییر دیتابیس زمانی کامل است که:

✔ Migration نوشته شده باشد.

✔ Rollback بررسی شده باشد.

✔ Index اضافه شده باشد.

✔ Queryها بررسی شده باشند.

✔ مستندات به‌روزرسانی شده باشند.

✔ Backup در نظر گرفته شده باشد.

✔ هیچ داده‌ای از بین نرود.

---

# پایان جلد هفتم