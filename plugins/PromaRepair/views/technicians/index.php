<div class="proma-repair">
  <header class="pr-hero"><span class="pr-hero-icon"><?= proma_icon('user', 'تعمیرکاران') ?></span><div class="pr-hero-copy"><small>Technician Registry</small><h2>تعمیرکاران</h2><p>حساب و پروفایل کاری تعمیرکاران را اینجا مدیریت کنید. تعمیرکار با نام کاربری و رمز خود از صفحه ورود سامانه وارد می‌شود.</p></div></header>
  <section class="pr-card"><div class="pr-card-head"><div><h3>افزودن تعمیرکار</h3><p>از همین‌جا حساب کاربری تعمیرکار را بسازید، یا یک کاربر فعالِ موجود را به فهرست اضافه کنید.</p></div><a class="btn secondary" href="<?= e(url('users')) ?>">مدیریت کاربران</a></div>
    <form method="post" action="<?= e(url('plugin/repair/technicians')) ?>" class="pr-technician-create-form"><?= csrf_field() ?><input type="hidden" name="create_technician_account" value="1">
      <div class="pr-grid three">
        <label class="pr-field"><span>نام کامل</span><input name="full_name" maxlength="190" required autocomplete="name"></label>
        <label class="pr-field"><span>نام کاربری</span><input name="username" minlength="3" maxlength="100" pattern="[A-Za-z0-9._-]{3,100}" required dir="ltr" autocomplete="username"><small>۳ تا ۱۰۰ نویسه؛ حروف انگلیسی، عدد، نقطه، خط تیره یا زیرخط</small></label>
        <label class="pr-field"><span>رمز عبور</span><input name="password" type="password" minlength="8" maxlength="200" required autocomplete="new-password"></label>
        <label class="pr-field"><span>موبایل <small>(اختیاری)</small></span><input name="mobile" inputmode="tel" autocomplete="tel"></label>
        <label class="pr-field"><span>ایمیل <small>(اختیاری)</small></span><input name="email" type="email" maxlength="190" autocomplete="email" dir="ltr"></label>
        <button class="btn primary align-self-end" type="submit">ساخت و ثبت تعمیرکار</button>
      </div>
    </form>
    <div class="pr-card-head"><div><h3>ثبت کاربر موجود</h3><p>کاربران فعال با نقش تعمیرکار را از فهرست انتخاب کنید.</p></div></div>
    <form method="post" action="<?= e(url('plugin/repair/technicians')) ?>" class="pr-grid two"><?= csrf_field() ?>
      <label class="pr-field"><span>کاربر فعال با نقش تعمیرکار</span><select name="user_id" required><option value="">انتخاب کاربر</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['full_name']) ?> · <?= e($user['mobile']) ?></option><?php endforeach; ?></select></label><button class="btn primary align-self-end">ثبت در فهرست تعمیرکاران</button>
    </form>
  </section>
  <section class="pr-card"><div class="pr-card-head"><div><h3>پروفایل کاری و دسترسی ارجاع</h3><p>کمیسیون ثبت‌شده فقط تنظیم کاری است؛ محاسبه و پرداخت آن نیازمند سیاست مالی جداگانه است.</p></div></div>
    <?php if (!$technicians): ?><div class="pr-empty">تعمیرکاری در فهرست ثبت نشده است.</div><?php else: ?><div class="pr-row-list">
      <?php foreach ($technicians as $item): $formId = 'technician-profile-' . (int) $item['user_id']; ?>
        <form id="<?= e($formId) ?>" method="post" action="<?= e(url('plugin/repair/technicians')) ?>" class="pr-technician-profile-form"><?= csrf_field() ?><input type="hidden" name="profile_user_id" value="<?= (int) $item['user_id'] ?>">
          <div class="pr-technician-profile-head"><span><strong><?= e($item['display_name'] ?: $item['full_name']) ?></strong><small><?= e(to_persian_digits($item['mobile'])) ?> · <?= e($item['internal_code'] ?: 'بدون کد داخلی') ?></small></span><label class="pr-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1"<?= checked($item['active'], 1) ?>> فعال برای ارجاع</label></div>
          <div class="pr-grid four"><label class="pr-field">تخصص<input name="specialty" maxlength="160" value="<?= e($item['specialty'] ?? '') ?>"></label><label class="pr-field">تلفن کاری<input name="phone" maxlength="32" value="<?= e($item['phone'] ?? '') ?>"></label><label class="pr-field">کد داخلی<input name="internal_code" maxlength="40" value="<?= e($item['internal_code'] ?? '') ?>"></label><label class="pr-field">مدل کمیسیون<select name="commission_model"><option value="fixed"<?= selected($item['commission_model'] ?? 'fixed', 'fixed') ?>>مبلغ ثابت</option><option value="percentage"<?= selected($item['commission_model'] ?? '', 'percentage') ?>>درصدی</option></select></label><label class="pr-field">مقدار کمیسیون<input name="commission_value" inputmode="decimal" value="<?= e($item['commission_value'] ?? '0') ?>"></label><label class="pr-field pr-span-all">یادداشت داخلی<textarea name="notes" maxlength="2000"><?= e($item['notes'] ?? '') ?></textarea></label><button class="btn secondary">ذخیره پروفایل</button></div>
        </form>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </section>
</div>
