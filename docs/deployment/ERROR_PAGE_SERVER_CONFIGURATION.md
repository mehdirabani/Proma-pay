# تنظیم سرور برای خطاهای اضطراری

## Apache

در سطح VirtualHost یا تنظیمات هاست، فقط برای خطاهای زیر fallback استاتیک تعیین کنید:

```apache
ErrorDocument 500 /static-errors/500.html
ErrorDocument 503 /static-errors/503.html
```

404 باید از Router برنامه عبور کند تا request id و پاسخ JSON صحیح باقی بماند. از هدایت 500 به `index.php` خودداری کنید تا حلقه خطا ایجاد نشود.

## Nginx

```nginx
error_page 500 502 504 /static-errors/500.html;
error_page 503 /static-errors/503.html;
location = /static-errors/500.html { internal; }
location = /static-errors/503.html { internal; }
```

تغییرات سرور باید ابتدا در محیط staging بررسی شوند.
