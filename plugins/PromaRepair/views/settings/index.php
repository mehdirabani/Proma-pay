<div class="proma-repair">
  <header class="pr-hero"><span class="pr-hero-icon"><i data-feather="settings"></i></span><div class="pr-hero-copy"><small>Module Settings</small><h2>تنظیمات تعمیرات</h2><p>تنظیمات شماره‌گذاری، امنیت، رسید و پنل مشتری</p></div><div class="pr-hero-actions"><a class="btn secondary" href="<?= e(url('plugin/repair/permissions')) ?>">مدیریت مجوزهای نقش‌ها</a></div></header>
  <form method="post" action="<?= e(url('plugin/repair/settings')) ?>" class="pr-grid"><?= csrf_field() ?>
    <section class="pr-card pr-span-all"><div class="pr-card-head"><div><h3>عمومی</h3></div></div><div class="pr-grid three">
      <label class="pr-field"><span>پیشوند کد رهگیری</span><input name="repair_number_prefix" value="<?= e($settings['repair_number_prefix']) ?>" required></label>
      <label class="pr-field"><span>گارانتی پیش‌فرض (روز)</span><input type="number" name="default_warranty_days" min="0" max="3650" value="<?= e($settings['default_warranty_days']) ?>"></label>
      <label class="pr-field"><span>زمان تخمینی پیش‌فرض (ساعت)</span><input type="number" name="default_estimated_hours" min="1" max="8760" value="<?= e($settings['default_estimated_hours']) ?>"></label>
    </div></section>
    <section class="pr-card pr-span-all"><div class="pr-card-head"><div><h3>امنیت رمز دستگاه</h3><p>رمز با AES-256-GCM رمزگذاری می‌شود. صفر یعنی حذف بلافاصله پس از تحویل و ۱− یعنی حذف دستی.</p></div></div><div class="pr-grid two">
      <label class="pr-field"><span>حذف خودکار پس از تحویل (روز)</span><input type="number" name="password_retention_days" min="-1" max="3650" value="<?= e($settings['password_retention_days']) ?>"></label>
      <div class="pr-alert">مشاهده رمز فقط با مجوز مستقل ممکن است و هر بار در Audit Log ثبت می‌شود.</div>
    </div></section>
    <section class="pr-card pr-span-all"><div class="pr-card-head"><div><h3>رسید و مرکز تعمیر</h3></div></div><div class="pr-grid two">
      <label class="pr-field"><span>نام مرکز تعمیر</span><input name="receipt_business_name" value="<?= e($settings['receipt_business_name']) ?>"></label>
      <label class="pr-field"><span>اطلاعات تماس</span><input name="receipt_contact" value="<?= e($settings['receipt_contact']) ?>"></label>
      <label class="pr-field"><span>اندازه چاپ رسید</span><select name="receipt_paper_size"><option value="A5"<?= selected($settings['receipt_paper_size'] ?? 'A5', 'A5') ?>>A5</option><option value="A4"<?= selected($settings['receipt_paper_size'] ?? 'A5', 'A4') ?>>A4</option></select></label>
      <label class="pr-field"><span>تعداد نسخه چاپی</span><select name="receipt_copy_mode"><option value="single"<?= selected($settings['receipt_copy_mode'] ?? 'single', 'single') ?>>یک نسخه</option><option value="duplicate"<?= selected($settings['receipt_copy_mode'] ?? 'single', 'duplicate') ?>>دو نسخه جداگانه (مشتری و مرکز)</option></select></label>
      <label class="pr-field pr-span-all"><span>نشانی مرکز</span><input name="store_address" value="<?= e($settings['store_address']) ?>"></label>
      <label class="pr-field"><span>ساعات کاری تحویل</span><input name="store_working_hours" value="<?= e($settings['store_working_hours']) ?>"></label>
      <label class="pr-field"><span>راهنمای تحویل</span><textarea name="pickup_instructions"><?= e($settings['pickup_instructions']) ?></textarea></label>
      <label class="pr-field pr-span-all"><span>پاورقی رسید</span><input name="receipt_footer" value="<?= e($settings['receipt_footer']) ?>"></label>
      <div class="pr-alert pr-span-all">برای بارگذاری امضای فروشنده از تنظیمات چاپ قرارداد استفاده کنید؛ امضای ذخیره‌شده در آنجا به‌صورت خودکار در بخش امضای فروشنده رسید تعمیرات نیز چاپ می‌شود. <a href="<?= e(url('settings/contracts/print')) ?>">تنظیم امضا و ظاهر چاپ قرارداد</a></div>
    </div></section>
    <section class="pr-card pr-span-all pr-receipt-template-workspace" data-repair-receipt-template>
      <div class="pr-card-head"><div><h3>قالب رسید تعمیرات</h3><p>نمای ویرایش بصری، پیش‌نمایش و HTML پیشرفته در اختیار شماست. برای درج اطلاعات متغیر روی هر مورد بزنید.</p></div>
        <div class="pr-receipt-template-modes" role="tablist" aria-label="حالت ویرایش قالب رسید">
          <button class="active" type="button" data-repair-receipt-mode="visual" aria-selected="true">ویرایش بصری</button>
          <button type="button" data-repair-receipt-mode="preview" aria-selected="false">پیش‌نمایش</button>
          <button type="button" data-repair-receipt-mode="source" aria-selected="false">HTML پیشرفته</button>
        </div>
      </div>
      <div class="pr-receipt-template-tools" aria-label="متغیرهای قالب رسید">
        <?php foreach (['business_name'=>'نام مرکز','contact'=>'اطلاعات تماس','address'=>'نشانی','tracking_code'=>'کد رهگیری','repair_number'=>'شماره پرونده','accepted_at'=>'تاریخ پذیرش','customer_name'=>'نام مشتری','customer_mobile'=>'موبایل مشتری','device'=>'دستگاه','device_identifiers'=>'شناسه دستگاه','status'=>'وضعیت','estimated_delivery'=>'زمان تحویل','issue_summary'=>'شرح خرابی','condition_notes'=>'یادداشت ظاهری','accessories'=>'لوازم همراه','conditions'=>'وضعیت ظاهری','warranty'=>'گارانتی','footer'=>'پاورقی'] as $key=>$label): ?>
          <button type="button" data-repair-receipt-variable="<?= e('{{' . $key . '}}') ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>
      <label class="pr-field pr-receipt-template-source"><span>محتوای قالب</span><textarea name="receipt_template_html" data-rich-editor data-rich-editor-height="500" data-repair-receipt-source><?= e($settings['receipt_template_html']) ?></textarea></label>
      <div class="pr-receipt-template-preview" data-repair-receipt-preview hidden><iframe title="پیش‌نمایش قالب رسید" data-repair-receipt-preview-frame sandbox=""></iframe></div>
      <p class="pr-receipt-template-status" data-repair-receipt-status aria-live="polite">ویرایش بصری آماده است.</p>
    </section>
    <section class="pr-card pr-span-all"><div class="pr-card-head"><div><h3>قوانین و شرایط پذیرش تعمیرات</h3><p>متن در پایین رسید چاپ می‌شود.</p></div></div>
      <label class="pr-field"><textarea class="pr-rich-source" name="receipt_terms" data-rich-editor><?= e($settings['receipt_terms']) ?></textarea></label>
    </section>
    <section class="pr-card pr-span-all"><div class="pr-card-head"><div><h3>متن اتحادیه تعمیرکاران موبایل</h3><p>متن رسمی از پیش‌فرض افزونه تهیه نشده؛ مدیر سیستم باید متن معتبر خود را وارد کند.</p></div></div>
      <label class="pr-field"><textarea name="receipt_union_text" rows="8"><?= e($settings['receipt_union_text']) ?></textarea></label>
      <small>نسخه <?= to_persian_digits($settings['receipt_union_version']) ?> · آخرین تغییر: <?= e($settings['receipt_union_updated_at'] ?: '—') ?> · شناسه کاربر: <?= to_persian_digits($settings['receipt_union_updated_by']) ?></small>
    </section>
    <section class="pr-card pr-span-all"><div class="pr-card-head"><div><h3>پنل مشتری</h3></div></div><div class="pr-checks">
      <?php foreach (['show_customer_financials'=>'نمایش مبلغ و پرداخت‌ها','show_customer_timeline'=>'نمایش گردش وضعیت','show_customer_warranty'=>'نمایش گارانتی','show_customer_delivery_estimate'=>'نمایش زمان تحویل تخمینی'] as $key=>$label): ?><label class="pr-check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= checked($settings[$key], '1') ?>> <?= e($label) ?></label><?php endforeach; ?>
    </div></section>
    <div class="pr-actions pr-span-all"><button class="btn primary" type="submit">ذخیره تنظیمات</button></div>
  </form>
</div>
