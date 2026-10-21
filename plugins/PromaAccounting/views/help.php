<?php require_once __DIR__ . '/components/ui.php'; $section = trim((string) ($_GET['section'] ?? 'quick-start')); ob_start(); ?><a class="btn btn-primary" href="<?= e(url('plugin/accounting/setup')) ?>"><i data-feather="play-circle"></i><span>شروع راه‌اندازی</span></a><?php $headerActions = trim(ob_get_clean()); ?>
<div class="proma-accounting proma-accounting-help-center">
  <?php pa_page_header('مرکز راهنمای داخلی', 'راهنمای حسابداری کاربران', 'پاسخ کوتاه و مرحله‌ای برای تنظیم کمیسیون، دفترکل و اصلاح عملیات مالی.', 'book-open', $headerActions); ?>
  <div class="proma-accounting-help-search"><i data-feather="search"></i><input type="search" data-accounting-help-search placeholder="جستجو در راهنمای حسابداری" aria-label="جستجو در راهنما"></div>
  <div class="proma-accounting-help-layout">
    <nav class="proma-accounting-help-nav" aria-label="فهرست راهنمای حسابداری"><?php foreach (['quick-start'=>'شروع سریع','concepts'=>'مفاهیم پایه','commission'=>'تنظیم کمیسیون','rules'=>'قوانین اختصاصی','transactions'=>'ثبت تراکنش','payments'=>'پرداخت و دریافت','ledger'=>'گردش حساب','reversal'=>'اصلاح و برگشت','troubleshooting'=>'رفع خطا','faq'=>'سؤالات متداول'] as $anchor => $label): ?><a href="#<?= e($anchor) ?>"<?= $section === $anchor ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
    <main class="proma-accounting-help-content">
      <section id="quick-start"><h3>شروع سریع</h3><ol><li>نوع و مقدار کمیسیون را مشخص کنید.</li><li>مبنای محاسبه و محدودیت‌ها را انتخاب کنید.</li><li>زمان شناسایی و تأیید مدیر را تنظیم کنید.</li><li>نتیجه را در پیش‌نمایش بررسی و ذخیره کنید.</li></ol></section>
      <section id="concepts"><h3>مفاهیم پایه</h3><p>قانون اختصاصی فعال با اولویت بالاتر بر قانون عمومی مقدم است. مانده مثبت مطالبه کاربر و مانده منفی بدهی او به مجموعه را نشان می‌دهد.</p></section>
      <section id="commission"><h3>تنظیم کمیسیون</h3><p><strong>درصدی:</strong> درصدی از مبنای انتخاب‌شده. <strong>ثابت:</strong> مبلغ یکسان برای هر فروش. حداقل و حداکثر پیش از گرد کردن اعمال می‌شوند.</p></section>
      <section id="rules"><h3>قوانین اختصاصی</h3><p>برای یک فروشنده می‌توانید قانون جداگانه تعریف کنید. در صورت وجود چند قانون فعال، اولویت عددی بالاتر انتخاب می‌شود.</p></section>
      <section id="transactions"><h3>هزینه، پاداش و کسورات</h3><ul><li>کمیسیون، پاداش و هزینه قابل پرداخت: افزایش مانده</li><li>کسورات و پرداخت به کاربر: کاهش مانده</li></ul></section>
      <section id="payments"><h3>پرداخت و دریافت</h3><p>پرداخت وجه به شخص با علامت منفی مانده را کاهش می‌دهد. دریافت وجه از شخص با علامت مثبت مانده را افزایش می‌دهد.</p></section>
      <section id="ledger"><h3>گردش حساب</h3><p>هر سند شامل مبلغ، اثر صریح، مانده قبل و بعد، تاریخ و ایجادکننده است. سابقه مالی ویرایش یا حذف نمی‌شود.</p></section>
      <section id="reversal"><h3>اصلاح و برگشت</h3><p>اصلاح با ثبت سند معکوس و علت اجباری انجام می‌شود. سند اصلی برای حسابرسی باقی می‌ماند و معکوس‌سازی تکراری مسدود است.</p></section>
      <section id="troubleshooting"><h3>رفع خطا</h3><p>اگر کمیسیون ساخته نشد، فروشنده، قانون فعال، زمان شناسایی و مبلغ مبنا را بررسی کنید. پیش‌نمایش تنظیمات هیچ داده‌ای ذخیره نمی‌کند.</p></section>
      <section id="faq" class="is-wide"><h3>سؤالات متداول</h3><details><summary>تغییر تنظیمات، کمیسیون قبلی را عوض می‌کند؟</summary><p>خیر؛ تنظیمات تازه فقط بر محاسبات آینده اثر دارد.</p></details><details><summary>آیا پیش‌نمایش سند مالی ثبت می‌کند؟</summary><p>خیر؛ پیش‌نمایش بدون ذخیره و بدون اثر بر دفترکل است.</p></details><details><summary>چرا سند را حذف نمی‌کنیم؟</summary><p>برای حفظ تاریخچه حسابرسی، اصلاح از طریق سند معکوس انجام می‌شود.</p></details></section>
    </main>
  </div>
</div>
