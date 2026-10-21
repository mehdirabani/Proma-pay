# Proma Pay — File Storage Policy

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی مدیریت فایل‌ها در پروژه Proma Pay است.

تمام فایل‌های آپلودی، فایل‌های خصوصی، فایل‌های موقت، رسیدهای پرداخت، مدارک هویتی، تصاویر چت، فایل‌های حقوقی، بکاپ‌ها، قالب‌های قرارداد و فایل‌های بروزرسانی باید مطابق این سند مدیریت شوند.

هیچ فایلی نباید بدون اعتبارسنجی، بدون مسیر امن، بدون چرخه عمر مشخص و بدون Permission ذخیره یا نمایش داده شود.

---

# Constitution

## Rule 1 — No Uploaded File Is Trusted

هیچ فایل آپلودی قابل اعتماد نیست.

تمام فایل‌ها باید قبل از ذخیره بررسی شوند:

```text
Extension
MIME Type
File Size
File Content
Upload Error
Image Validity
Security Risk
```

---

## Rule 2 — Private Files Must Not Be Public

فایل‌های خصوصی نباید داخل مسیر Public ذخیره شوند.

موارد خصوصی:

```text
مدارک هویتی
رسیدهای پرداخت
فایل‌های حقوقی
فایل‌های قرارداد
بکاپ‌ها
فایل‌های Import
فایل‌های Export خصوصی
تصاویر چت خصوصی
```

این فایل‌ها باید در مسیر Private Storage ذخیره شوند و فقط از طریق Controller مجاز قابل مشاهده یا دانلود باشند.

---

## Rule 3 — Direct File URL Is Forbidden For Private Files

نمایش مستقیم فایل خصوصی با URL ممنوع است.

ممنوع:

```text
/uploads/identity/customer-card.png
/storage/receipts/receipt.jpg
/backups/backup.zip
```

صحیح:

```text
/files/view/{file_id}
/files/download/{file_id}
```

در این حالت Controller باید Permission را بررسی کند.

---

## Rule 4 — Every File Must Have Metadata

هر فایل ذخیره‌شده باید در دیتابیس Metadata داشته باشد.

Metadata باید مشخص کند:

```text
مالک فایل کیست؟
فایل مربوط به چه موجودیتی است؟
نوع فایل چیست؟
مسیر امن فایل کجاست؟
وضعیت فایل چیست؟
چه زمانی باید حذف شود؟
چه کسی آن را آپلود کرده؟
```

---

## Rule 5 — File Lifecycle Must Be Explicit

هر نوع فایل باید چرخه عمر مشخص داشته باشد.

هیچ فایلی نباید بدون قانون نگهداری یا حذف در سیستم باقی بماند.

---

# File Categories

انواع اصلی فایل در Proma Pay:

```text
identity_document
payment_receipt
chat_image
legal_document
contract_document
contract_template_asset
profile_avatar
system_logo
backup_file
update_package
import_file
export_file
temporary_file
```

---

# Storage Structure

ساختار پیشنهادی Storage:

```text
/storage
│
├── private/
│   ├── identity-documents/
│   ├── payment-receipts/
│   ├── chat-images/
│   ├── legal-documents/
│   ├── contract-documents/
│   ├── backups/
│   ├── updates/
│   ├── imports/
│   ├── exports/
│   └── temp/
│
├── public/
│   ├── logos/
│   ├── avatars/
│   └── ui/
│
└── logs/
```

---

# Public vs Private

## Public Files

فقط فایل‌هایی که محرمانه نیستند می‌توانند Public باشند.

نمونه:

```text
لوگوی سامانه
آواتار عمومی
آیکون‌ها
فایل‌های UI
```

---

## Private Files

فایل‌های زیر همیشه Private هستند:

```text
مدارک هویتی
رسیدهای پرداخت
تصاویر چت
فایل‌های حقوقی
قراردادهای تولیدشده
بکاپ‌ها
فایل‌های Import
فایل‌های Export مدیریتی
فایل‌های Update
```

---

# File Metadata Table

سیستم باید یک جدول مرکزی برای فایل‌ها داشته باشد.

نام پیشنهادی:

```text
files
```

فیلدهای پیشنهادی:

```text
id
file_type
disk
visibility
original_name
stored_name
relative_path
mime_type
extension
size_bytes
checksum
owner_user_id
owner_customer_id
entity_type
entity_id
status
uploaded_by_user_id
uploaded_by_customer_id
reviewed_by
reviewed_at
expires_at
deleted_at
created_at
updated_at
metadata
```

---

# File Visibility Values

مقادیر پیشنهادی:

```text
public
private
restricted
temporary
```

---

# File Status Values

مقادیر پیشنهادی:

```text
pending
active
approved
rejected
archived
expired
deleted
failed
```

---

# Allowed File Types

## Identity Documents

فرمت‌های مجاز:

```text
jpg
jpeg
png
webp
```

حداکثر حجم:

```text
1MB
```

---

## Payment Receipts

فرمت‌های مجاز:

```text
jpg
jpeg
png
webp
```

حداکثر حجم:

```text
1MB
```

---

## Chat Images

فرمت‌های مجاز:

```text
jpg
jpeg
png
webp
```

حداکثر حجم:

```text
1MB
```

---

## Legal Documents

فرمت‌های مجاز:

```text
jpg
jpeg
png
webp
pdf
doc
docx
```

حداکثر حجم باید از Settings قابل تنظیم باشد.

پیشنهاد پیش‌فرض:

```text
5MB
```

---

## Contract Documents

فرمت‌های مجاز:

```text
pdf
```

---

## Backup Files

فرمت‌های مجاز:

```text
zip
sql
tar
gz
```

Backupها نباید مستقیم از Public قابل دانلود باشند.

---

## Update Packages

فرمت مجاز:

```text
zip
```

فایل Update باید شامل:

```text
update.json
```

باشد.

---

# Upload Validation Rules

تمام Uploadها باید این موارد را بررسی کنند:

```text
فایل واقعاً ارسال شده باشد.
آپلود PHP خطا نداشته باشد.
حجم فایل از حد مجاز بیشتر نباشد.
پسوند فایل مجاز باشد.
MIME Type معتبر باشد.
نام فایل امن‌سازی شود.
محتوای تصویر در صورت تصویر بودن معتبر باشد.
فایل اجرایی نباشد.
فایل داخل مسیر امن ذخیره شود.
Metadata در دیتابیس ثبت شود.
```

---

# Forbidden Extensions

پسوندهای زیر همیشه ممنوع هستند:

```text
php
phtml
phar
exe
bat
sh
cmd
js
html
htm
svg
cgi
pl
py
jar
```

نکته:

SVG فقط برای فایل‌های سیستمی تأییدشده و نه آپلود کاربر قابل استفاده است.

---

# MIME Validation

بررسی Extension کافی نیست.

MIME Type هم باید بررسی شود.

برای تصویرها باید از روش امن استفاده شود:

```text
finfo_file
getimagesize
```

اگر MIME با Extension سازگار نبود، فایل رد شود.

---

# File Naming Rules

نام فایل ذخیره‌شده نباید همان نام اصلی کاربر باشد.

الگوی پیشنهادی:

```text
{file_type}_{entity_id}_{random_hash}.{extension}
```

نمونه:

```text
payment_receipt_245_a8f93c1d.webp
```

---

# Original File Name

نام اصلی فایل فقط برای نمایش داخلی ذخیره شود.

نام اصلی نباید برای مسیر فایل استفاده شود.

---

# Path Rules

مسیر فایل نباید از ورودی کاربر ساخته شود.

ممنوع:

```php
$path = $_POST['folder'] . '/' . $_FILES['file']['name'];
```

صحیح:

```php
$path = FileStorageService::buildSafePath($fileType, $entityId, $extension);
```

---

# Image Processing

تمام تصاویر آپلودی باید در صورت امکان پردازش شوند:

```text
Resize
Compress
Strip Metadata
Optimize
Generate Thumbnail
```

EXIF Metadata باید حذف شود.

---

# Identity Document Lifecycle

چرخه عمر مدارک هویتی:

```text
Upload New Document
↓
Status = pending
↓
Admin Review
↓
Approved:
    New Document = active
    Old Document = deleted
    Customer Blue Tick = active
Rejected:
    New Document = deleted
    Old Document = unchanged
    Customer Blue Tick = unchanged
```

---

# Identity Document Rules

قوانین مدارک هویتی:

```text
مدرک جدید تا زمان تأیید جایگزین مدرک قبلی نمی‌شود.
اگر مدرک جدید رد شود، مدرک قبلی باقی می‌ماند.
پس از تأیید مدرک جدید، مدرک قبلی از سرور حذف می‌شود.
فقط مدیریت مجاز به تأیید یا رد است.
تمام عملیات باید Audit Log داشته باشد.
```

---

# Payment Receipt Lifecycle

چرخه عمر رسید پرداخت کارت‌به‌کارت:

```text
Upload Receipt
↓
Status = pending_review
↓
Admin Review
↓
Approved:
    Payment = approved
    Installment = paid / partially_paid
    Receipt File = deleted
Rejected:
    Payment = waiting / rejected
    Installment = unpaid / waiting
    Receipt File = deleted
```

---

# Payment Receipt Rules

قوانین رسید پرداخت:

```text
رسید فقط تا زمان بررسی نگهداری شود.
پس از تأیید یا رد، فایل رسید باید حذف شود.
Metadata پرداخت و نتیجه بررسی باقی بماند.
مشتری نباید بعد از بررسی فایل رسید را ببیند.
مدیر باید قبل از بررسی بتواند فایل را مشاهده کند.
```

---

# Chat Image Lifecycle

چرخه عمر تصاویر چت:

```text
Upload
↓
Available For Limited Time
↓
Auto Delete After Configured Days
↓
Message Remains
↓
File Placeholder Is Shown
```

مدت پیش‌فرض:

```text
7 days
```

این مقدار باید از Settings قابل تغییر باشد.

---

# Chat Image Rules

پس از حذف فایل چت، پیام نباید حذف شود.

به جای فایل حذف‌شده نمایش داده شود:

```text
این فایل طبق سیاست نگهداری حذف شده است.
```

---

# Legal Document Lifecycle

چرخه عمر فایل‌های حقوقی:

```text
Upload
↓
Active
↓
Linked To Legal Case
↓
Archived When Case Closed
```

فایل‌های حقوقی نباید بدون دلیل حذف شوند.

حذف فایل حقوقی فقط با دسترسی مدیر اصلی یا مسئول حقوقی مجاز است و باید Audit Log داشته باشد.

---

# Contract Document Lifecycle

قراردادهای تولیدشده:

```text
Generate PDF
↓
Store Private
↓
Link To Contract
↓
Available To Authorized Users
```

مشتری فقط قرارداد خودش را ببیند.

مدیر و نقش‌های مجاز بتوانند مشاهده کنند.

---

# Backup File Lifecycle

چرخه عمر بکاپ:

```text
Create Backup
↓
Store Private
↓
Admin Download
↓
Retention Policy
↓
Archive / Delete
```

Backup باید شامل موارد زیر باشد:

```text
Database
Settings
Contract Templates
Private File Metadata
Uploaded Files
```

---

# Backup File Rules

فایل بکاپ نباید داخل Public باشد.

دانلود بکاپ فقط از طریق Controller امن انجام شود.

هر دانلود بکاپ باید Audit Log داشته باشد.

---

# Update Package Lifecycle

چرخه عمر فایل بروزرسانی:

```text
Upload Update ZIP
↓
Validate ZIP
↓
Validate update.json
↓
Backup Current System
↓
Apply Update
↓
Archive Or Delete Update Package
```

---

# Update Package Rules

فایل Update باید قبل از اجرا بررسی شود:

```text
فرمت ZIP معتبر باشد.
update.json وجود داشته باشد.
نسخه معتبر باشد.
فایل‌های خطرناک نداشته باشد.
SQL خطرناک نداشته باشد.
Checksum معتبر باشد در صورت وجود.
```

---

# Temporary Files

فایل‌های موقت باید تاریخ انقضا داشته باشند.

هیچ فایل موقتی نباید دائمی بماند.

مسیر:

```text
/storage/private/temp/
```

پاکسازی باید با Job انجام شود.

---

# Export Files

فایل‌های Export خصوصی باید:

```text
در Private Storage ذخیره شوند.
تاریخ انقضا داشته باشند.
فقط برای کاربر ایجادکننده قابل دانلود باشند.
بعد از انقضا حذف شوند.
```

---

# Import Files

فایل‌های Import باید:

```text
در Private Storage ذخیره شوند.
اعتبارسنجی شوند.
پس از پردازش موفق حذف یا Archive شوند.
در صورت خطا Log شوند.
```

---

# File Access Rules

برای مشاهده یا دانلود هر فایل خصوصی باید بررسی شود:

```text
آیا کاربر Login است؟
آیا فایل وجود دارد؟
آیا فایل حذف نشده؟
آیا کاربر مالک فایل است یا Permission دارد؟
آیا فایل منقضی نشده؟
آیا نوع فایل اجازه نمایش دارد؟
```

---

# File Download Controller

دانلود فایل خصوصی باید از طریق Controller انجام شود.

Controller باید:

```text
Permission را بررسی کند.
MIME صحیح ارسال کند.
Content-Disposition مناسب ارسال کند.
مسیر واقعی فایل را مخفی نگه دارد.
Download Log ثبت کند در موارد حساس.
```

---

# File Preview Rules

Preview فقط برای فایل‌های مجاز انجام شود.

تصاویر:

```text
jpg
jpeg
png
webp
```

PDF:

```text
pdf
```

برای فایل‌های Office بهتر است دانلود انجام شود، نه Preview مستقیم.

---

# Security Rules

موارد امنیتی الزامی:

```text
عدم ذخیره فایل خصوصی در Public
عدم اجرای فایل آپلودی
عدم اعتماد به Extension
عدم نمایش مسیر واقعی فایل
عدم نمایش فایل بدون Permission
حذف Metadata تصویر
تغییر نام فایل
ثبت Log برای فایل‌های حساس
```

---

# Access By Role

## Admin

دسترسی به تمام فایل‌های مجاز مدیریتی.

---

## Operator

دسترسی محدود به فایل‌های موردنیاز پیگیری.

اپراتور نباید مدارک هویتی حساس را بدون Permission ببیند.

---

## Lawyer

دسترسی به فایل‌های حقوقی پرونده‌های مجاز.

---

## Customer

فقط دسترسی به فایل‌های خودش.

---

# File Deletion Rules

حذف فایل باید از طریق FileStorageService انجام شود.

ممنوع:

```php
unlink($path);
```

صحیح:

```php
FileStorageService::delete($fileId, $actorId);
```

حذف باید:

```text
Permission بررسی کند.
فایل فیزیکی را حذف کند.
Metadata را updated کند.
Log ثبت کند.
در صورت شکست، خطا را مدیریت کند.
```

---

# Soft Delete vs Physical Delete

## Soft Delete

برای Metadata فایل استفاده شود.

```text
deleted_at
status = deleted
```

---

## Physical Delete

برای فایل فیزیکی در موارد لازم انجام شود.

نمونه:

```text
رسید پرداخت پس از بررسی
مدرک ردشده
تصویر چت منقضی‌شده
فایل موقت
```

---

# Cleanup Jobs

سیستم باید Jobهای پاکسازی داشته باشد.

نمونه Jobها:

```text
DeleteExpiredChatImagesJob
DeleteReviewedPaymentReceiptsJob
DeleteRejectedIdentityDocumentsJob
DeleteExpiredTemporaryFilesJob
DeleteExpiredExportFilesJob
ArchiveOldLogsJob
```

---

# FileService Responsibilities

سرویس مرکزی پیشنهادی:

```text
FileStorageService
```

مسئولیت‌ها:

```text
Validate Upload
Store File
Generate Safe Name
Generate Safe Path
Create Metadata
Check Permission
Download File
Preview File
Delete File
Cleanup Expired Files
Calculate Checksum
Optimize Image
```

---

# ImageService Responsibilities

سرویس پیشنهادی:

```text
ImageProcessingService
```

مسئولیت‌ها:

```text
Resize
Compress
Convert
Strip EXIF
Generate Thumbnail
Validate Image
```

---

# FilePermissionService Responsibilities

سرویس پیشنهادی:

```text
FilePermissionService
```

مسئولیت‌ها:

```text
بررسی مالکیت فایل
بررسی نقش کاربر
بررسی موجودیت مرتبط
بررسی دسترسی مشتری
بررسی دسترسی حقوقی
بررسی دسترسی مدیریت
```

---

# Logging Rules

عملیات زیر باید Log داشته باشند:

```text
آپلود فایل حساس
مشاهده فایل حساس
دانلود بکاپ
حذف فایل
رد مدرک
تأیید مدرک
رد رسید
تأیید رسید
آپلود فایل حقوقی
حذف فایل حقوقی
آپلود Update
اجرای Update
```

---

# Audit Rules

موارد زیر باید Audit Log داشته باشند:

```text
تأیید مدرک هویتی
رد مدرک هویتی
تأیید رسید پرداخت
رد رسید پرداخت
دانلود بکاپ
حذف فایل حقوقی
حذف فایل قرارداد
آپلود بسته بروزرسانی
بازیابی بکاپ
```

---

# Error Handling Rules

اگر آپلود شکست خورد:

```text
فایل موقت حذف شود.
پیام مناسب نمایش داده شود.
خطای فنی Log شود.
وضعیت دیتابیس ناقص نماند.
```

اگر ذخیره Metadata شکست خورد:

```text
فایل فیزیکی حذف شود.
Transaction Rollback شود.
```

اگر حذف فایل فیزیکی شکست خورد:

```text
Log ثبت شود.
وضعیت فایل pending_delete شود.
Job پاکسازی دوباره تلاش کند.
```

---

# Database Transaction Rules

عملیات زیر باید Transaction داشته باشند:

```text
آپلود فایل + ثبت Metadata
تأیید رسید + حذف فایل + تغییر وضعیت پرداخت
رد رسید + حذف فایل + تغییر وضعیت پرداخت
تأیید مدرک + حذف مدرک قبلی + فعال‌سازی مدرک جدید
رد مدرک + حذف مدرک جدید
آپلود فایل حقوقی + ثبت Log حقوقی
```

---

# Settings

مقادیر زیر باید از Settings قابل تغییر باشند:

```text
حداکثر حجم مدرک هویتی
حداکثر حجم رسید پرداخت
حداکثر حجم تصویر چت
حداکثر حجم فایل حقوقی
مدت نگهداری تصاویر چت
مدت نگهداری فایل‌های Export
فعال بودن Image Optimization
کیفیت فشرده‌سازی تصویر
مسیر Storage در صورت نیاز
```

---

# Forbidden Patterns

موارد زیر ممنوع هستند:

```php
move_uploaded_file($_FILES['file']['tmp_name'], 'uploads/' . $_FILES['file']['name']);
```

```php
echo '<img src="/uploads/' . $fileName . '">';
```

```php
unlink($_GET['path']);
```

```php
$filePath = $_GET['file'];
readfile($filePath);
```

```php
if ($_FILES['file']['type'] === 'image/png') {
    // trust MIME from browser
}
```

---

# Correct Upload Pattern

الگوی صحیح:

```php
$file = FileStorageService::storeUploadedFile(
    uploadedFile: $_FILES['receipt'],
    fileType: 'payment_receipt',
    entityType: 'payment',
    entityId: $paymentId,
    uploadedBy: $currentUserId
);
```

---

# Correct Download Pattern

الگوی صحیح:

```php
$file = FileStorageService::findOrFail($fileId);

FilePermissionService::ensureCanView($currentUser, $file);

return FileStorageService::stream($file);
```

---

# Correct Delete Pattern

الگوی صحیح:

```php
FileStorageService::deleteFile(
    fileId: $fileId,
    actorId: $currentUserId,
    reason: 'payment_receipt_review_completed'
);
```

---

# Implementation Rules

Codex هنگام پیاده‌سازی هر Feature باید بررسی کند:

```text
آیا فایل Public است یا Private؟
آیا Upload نیاز به Validation دارد؟
حداکثر حجم فایل چیست؟
فرمت‌های مجاز چیست؟
آیا فایل باید Metadata داشته باشد؟
آیا فایل تاریخ انقضا دارد؟
آیا فایل بعداً باید حذف شود؟
آیا دسترسی دانلود/Preview بررسی شده؟
آیا فایل باید در Backup بیاید؟
آیا فایل باید Log یا Audit داشته باشد؟
آیا Image Optimization لازم است؟
آیا حذف فایل باید Job داشته باشد؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ فایل‌های خصوصی داخل Public ذخیره نمی‌شوند.
□ Upload اعتبارسنجی کامل دارد.
□ MIME و Extension بررسی شده‌اند.
□ حجم فایل بررسی شده است.
□ نام فایل امن تولید می‌شود.
□ مسیر فایل از ورودی کاربر ساخته نمی‌شود.
□ Metadata در دیتابیس ثبت می‌شود.
□ Permission دانلود و مشاهده بررسی می‌شود.
□ مسیر واقعی فایل نمایش داده نمی‌شود.
□ چرخه عمر فایل مشخص است.
□ حذف فایل از طریق FileStorageService انجام می‌شود.
□ فایل‌های موقت پاکسازی می‌شوند.
□ عملیات حساس Log دارد.
□ عملیات حساس Audit دارد.
□ خطاهای آپلود مدیریت شده‌اند.
□ رسید پرداخت بعد از بررسی حذف می‌شود.
□ تصاویر چت بعد از مدت مشخص حذف می‌شوند.
□ مدارک هویتی مطابق Workflow مدیریت می‌شوند.
```

---

# Definition of Done

یک Feature از نظر File Storage زمانی کامل است که:

```text
✔ فایل‌ها امن ذخیره شوند.
✔ فایل‌های خصوصی مستقیم قابل دسترسی نباشند.
✔ Upload کامل Validate شود.
✔ Permission مشاهده و دانلود بررسی شود.
✔ Metadata فایل ثبت شود.
✔ چرخه عمر فایل مشخص باشد.
✔ فایل‌های موقت حذف شوند.
✔ فایل‌های حساس Log و Audit داشته باشند.
✔ خطاها بدون ایجاد وضعیت ناقص مدیریت شوند.
✔ قوانین حذف و نگهداری رعایت شوند.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
Cloud Storage
S3 Compatible Storage
CDN For Public Assets
Encrypted Private Files
Virus Scanning
Advanced File Preview
Watermark For Legal Documents
File Versioning
File Access History
Signed Temporary Download Links
Storage Quota
Multi-Tenant Storage Isolation
AI Document Classification
OCR For Identity Documents
```

---

# End of File