<?php $section = trim((string) ($_GET['section'] ?? 'quick-start')); ?>
<div class="proma-accounting proma-accounting-help-center">
  <header class="proma-accounting-page-header"><div><span class="proma-accounting-eyebrow">مرکز راهنمای داخلی</span><h2>آموزش راه‌اندازی حسابداری کاربران</h2><p>راهنمای کامل عملیات مالی پرما پی، بدون نیاز به اینترنت.</p></div><a class="btn btn-primary" href="<?= e(url('plugin/accounting/setup')) ?>"><i data-feather="play-circle"></i><span>شروع راه‌اندازی</span></a></header>
  <div class="proma-accounting-help-layout">
    <nav class="proma-accounting-help-nav" aria-label="فهرست راهنمای حسابداری">
      <?php foreach (['quick-start' => 'شروع سریع', 'concepts' => 'مفاهیم پایه', 'commission' => 'تنظیم کمیسیون', 'rules' => 'قوانین اختصاصی', 'transactions' => 'ثبت هزینه، پاداش و کسورات', 'payments' => 'پرداخت و دریافت', 'ledger' => 'گردش حساب', 'reversal' => 'اصلاح و برگشت', 'reports' => 'گزارش‌ها', 'troubleshooting' => 'رفع خطا', 'faq' => 'سؤالات متداول'] as $anchor => $label): ?><a href="#<?= e($anchor) ?>"<?= $section === $anchor ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <main class="proma-accounting-help-content">
      <section id="quick-start"><h3>شروع سریع</h3><ol><li>نوع کمیسیون پیش‌فرض را انتخاب کنید.</li><li>مقدار و مبنای محاسبه را تعیین کنید.</li><li>در صورت نیاز کف، سقف و گردکردن را فعال کنید.</li><li>زمان شناسایی و وضعیت تأیید را مشخص کنید.</li><li>با پیش‌نمایش، نتیجه را پیش از ذخیره بررسی کنید.</li></ol></section>
      <section id="concepts"><h3>مفاهیم پایه</h3><p>قانون عمومی وقتی استفاده می‌شود که برای فروشنده قانون اختصاصی وجود نداشته باشد. قانون اختصاصی فعال با اولویت بالاتر، جایگزین قانون عمومی می‌شود.</p></section>
      <section id="commission"><h3>تنظیم کمیسیون</h3><p><strong>درصدی:</strong> درصدی از مبنای انتخاب‌شده. <strong>ثابت:</strong> مبلغ ثابت برای هر فروش.</p><p>مبنا می‌تواند کل قرارداد، مبلغ پس از پیش‌پرداخت، سود قرارداد یا مبلغ واقعاً وصول‌شده باشد. حداقل نقش کف و حداکثر نقش سقف را دارد. گردکردن همیشه در آخر اجرا می‌شود.</p></section>
      <section id="rules"><h3>قوانین اختصاصی کاربران</h3><p>قانون اختصاصی فروشنده بر قانون پیش‌فرض مقدم است. اگر چند قانون فعال برای یک فروشنده وجود داشته باشد، اولویت بالاتر انتخاب می‌شود.</p></section>
      <section id="transactions"><h3>هزینه، پاداش و کسورات</h3><ul><li>کمیسیون، پاداش و هزینه قابل پرداخت: افزایش مانده</li><li>کسورات: کاهش مانده</li></ul></section>
      <section id="payments" class="proma-accounting-balance-guide"><h3>پرداخت و دریافت</h3><div><strong>پرداخت وجه به شخص</strong><span>مانده حساب شخص را کاهش می‌دهد.</span></div><div><strong>دریافت وجه از شخص</strong><span>مانده حساب شخص را افزایش می‌دهد.</span></div></section>
      <section id="ledger"><h3>گردش حساب کاربران</h3><p>هر سند شامل مانده قبل، نوع اثر، مبلغ و مانده بعد است. اسناد تاریخی ویرایش یا حذف نمی‌شوند.</p></section>
      <section id="reversal"><h3>اصلاح و برگشت تراکنش</h3><p>اصلاح با ثبت سند معکوس انجام می‌شود؛ سند اصلی برای حسابرسی حفظ می‌گردد و امکان معکوس‌سازی تکراری وجود ندارد.</p></section>
      <section id="reports"><h3>گزارش‌ها</h3><p>داشبورد، حساب کاربران، فروش‌ها، کمیسیون‌ها و دفترکل، وضعیت مالی را از سندهای ثبت‌شده نمایش می‌دهند.</p></section>
      <section id="troubleshooting"><h3>رفع خطا</h3><p>اگر کمیسیون ساخته نشد، فروشنده، زمان شناسایی، قانون فعال و مبلغ مبنا را بررسی کنید. preview تنظیمات به تشخیص قانون و مبلغ کمک می‌کند.</p></section>
      <section id="faq"><h3>سؤالات متداول</h3><details><summary>تغییر تنظیمات، کمیسیون قبلی را عوض می‌کند؟</summary><p>خیر. تنظیمات جدید فقط برای محاسبات آینده است.</p></details><details><summary>آیا preview سند مالی ثبت می‌کند؟</summary><p>خیر. preview کاملاً بدون ذخیره و بدون اثر بر دفترکل است.</p></details><details><summary>پرداخت به کاربر چه اثری دارد؟</summary><p>مانده حساب کاربر را کاهش می‌دهد.</p></details></section>
    </main>
  </div>
</div>
