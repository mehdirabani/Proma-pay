<?php
$summary = $summary ?? [];
$rules = $rules ?? [];
$tiers = $tiers ?? [];
$systemRules = array_values(array_filter($rules, static fn ($rule) => ($rule['source_type'] ?? '') === 'system'));
$manualRules = array_values(array_filter($rules, static fn ($rule) => ($rule['source_type'] ?? '') === 'manual'));
?>
<section class="card proma-filter-card">
  <div class="card-header card-no-border"><div class="header-top"><div><h2>باشگاه امتیاز مشتریان</h2><p class="text-muted">فعالیت‌های مشتری امتیاز می‌سازند؛ جمع امتیاز، سطح فعلی و مدال‌های مرحله‌ای را تعیین می‌کند.</p></div><a class="btn secondary" href="<?= e(url('customers')) ?>">فهرست مشتریان</a></div></div>
  <div class="card-body">
    <div class="proma-customer-card-metrics">
      <span><strong><?= to_persian_digits($summary['points_issued'] ?? 0) ?></strong><small>امتیاز خالص مشتریان</small></span>
      <span><strong><?= to_persian_digits($summary['customers_with_points'] ?? 0) ?></strong><small>مشتری دارای سابقه امتیاز</small></span>
      <span><strong><?= to_persian_digits($summary['medals_awarded'] ?? 0) ?></strong><small>مدال سطحی دریافت‌شده</small></span>
      <span><strong><?= to_persian_digits($summary['active_tiers'] ?? 0) ?></strong><small>سطح فعال</small></span>
    </div>
    <div class="notice info mt-3">امتیازهای سیستمی از رویدادهای جدید محاسبه می‌شوند. برای فعالیت‌های قبلی یا فعالیت‌های خارج از سامانه، مدیر می‌تواند با ثبت شرح، امتیاز دستی بدهد. سابقه امتیازها به‌صورت دفترکل نگهداری می‌شود.</div>
  </div>
</section>

<section class="card">
  <div class="card-header card-no-border"><div><h2>فعالیت‌های خودکار</h2><p class="text-muted">با تغییر امتیاز یا خاموش کردن هر فعالیت، سابقه قبلی مشتری‌ها دست‌نخورده می‌ماند.</p></div></div>
  <div class="table-wrap"><table>
    <thead><tr><th>فعالیت</th><th>شرح</th><th>امتیاز</th><th>وضعیت</th><th>عملیات</th></tr></thead>
    <tbody>
    <?php foreach ($systemRules as $rule): ?>
      <tr>
        <td><strong><?= e($rule['title']) ?></strong><br><small class="text-muted"><?= e($rule['activity_key']) ?></small></td>
        <td><?= e($rule['description'] ?? '') ?></td>
        <td><form class="form-grid two" method="post" action="<?= e(url('loyalty/storeRule')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $rule['id'] ?>"><label class="sr-only" for="rule-points-<?= (int) $rule['id'] ?>">امتیاز <?= e($rule['title']) ?></label><input id="rule-points-<?= (int) $rule['id'] ?>" name="points" type="number" min="1" max="10000" value="<?= (int) $rule['points'] ?>" required><input type="hidden" name="is_active" value="<?= (int) $rule['is_active'] ?>"><button class="btn small secondary" type="submit">ذخیره</button></form></td>
        <td><span class="badge <?= !empty($rule['is_active']) ? 'badge-light-success' : 'muted' ?>"><?= !empty($rule['is_active']) ? 'فعال' : 'غیرفعال' ?></span></td>
        <td><form method="post" action="<?= e(url('loyalty/toggleRule/' . (int) $rule['id'])) ?>"><?= csrf_field() ?><button class="btn small <?= !empty($rule['is_active']) ? 'secondary' : 'success' ?>" type="submit"><?= !empty($rule['is_active']) ? 'غیرفعال کردن' : 'فعال کردن' ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$systemRules): ?><tr><td colspan="5" class="empty">فعالیت خودکاری تعریف نشده است.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<section class="card">
  <div class="card-header card-no-border"><div><h2>فعالیت‌های قابل ثبت توسط مدیر</h2><p class="text-muted">برای هر فعالیت یک امتیاز ثابت تعریف کنید؛ هنگام ثبت برای مشتری، شرح انجام فعالیت الزامی است.</p></div></div>
  <div class="table-wrap"><table>
    <thead><tr><th>فعالیت</th><th>شناسه</th><th>امتیاز</th><th>وضعیت</th><th>ذخیره</th></tr></thead>
    <tbody>
    <?php foreach ($manualRules as $rule): ?>
      <tr><td><?= e($rule['title']) ?><br><small class="text-muted"><?= e($rule['description'] ?? '') ?></small></td><td><?= e($rule['activity_key']) ?></td>
        <td><form class="form-grid two" method="post" action="<?= e(url('loyalty/storeRule')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $rule['id'] ?>"><label class="sr-only" for="manual-points-<?= (int) $rule['id'] ?>">امتیاز فعالیت <?= e($rule['title']) ?></label><input id="manual-points-<?= (int) $rule['id'] ?>" name="points" type="number" min="1" max="10000" value="<?= (int) $rule['points'] ?>" required><input type="hidden" name="is_active" value="<?= (int) $rule['is_active'] ?>"><button class="btn small secondary" type="submit">ذخیره امتیاز</button></form></td>
        <td><form method="post" action="<?= e(url('loyalty/toggleRule/' . (int) $rule['id'])) ?>"><?= csrf_field() ?><button class="btn small <?= !empty($rule['is_active']) ? 'secondary' : 'success' ?>" type="submit"><?= !empty($rule['is_active']) ? 'فعال · خاموش کردن' : 'غیرفعال · فعال کردن' ?></button></form></td><td>—</td></tr>
    <?php endforeach; ?>
    <?php if (!$manualRules): ?><tr><td colspan="5" class="empty">هنوز فعالیت دستی تعریف نشده است.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <div class="card-body"><form class="form-grid three" method="post" action="<?= e(url('loyalty/storeRule')) ?>">
    <?= csrf_field() ?>
    <label>شناسه کوتاه انگلیسی<input name="activity_key" placeholder="مثلاً referral" pattern="[A-Za-z0-9_-]+" required><small>سیستم به‌صورت خودکار پیشوند manual_ را اضافه می‌کند.</small></label>
    <label>نام فعالیت<input name="title" maxlength="150" required placeholder="معرفی مشتری جدید"></label>
    <label>امتیاز<input name="points" type="number" min="1" max="10000" value="10" required></label>
    <label class="full">شرح کوتاه<textarea name="description" maxlength="255" rows="2" placeholder="شرایطی که این فعالیت امتیاز می‌گیرد"></textarea></label>
    <div class="full"><button class="btn" type="submit">افزودن فعالیت</button></div>
  </form></div>
</section>

<section class="card">
  <div class="card-header card-no-border"><div><h2>سطح‌ها و مدال‌های مبتنی بر امتیاز</h2><p class="text-muted">عبور مشتری از حداقل امتیاز هر سطح، همان مدال را یک‌بار به سابقه او اضافه می‌کند.</p></div></div>
  <div class="table-wrap"><table>
    <thead><tr><th>سطح</th><th>مدال</th><th>حداقل امتیاز</th><th>توضیح</th><th>وضعیت</th><th>عملیات</th></tr></thead>
    <tbody>
    <?php foreach ($tiers as $tier): ?>
      <tr><td><strong><?= e($tier['title']) ?></strong><br><small class="text-muted"><?= e($tier['slug']) ?></small></td><td><?= e($tier['medal_title']) ?></td><td><?= to_persian_digits($tier['min_points']) ?></td><td><?= e($tier['description'] ?? '') ?></td><td><?= !empty($tier['is_active']) ? 'فعال' : 'غیرفعال' ?></td><td><details><summary class="btn small secondary">ویرایش</summary><form class="form-grid two mt-2" method="post" action="<?= e(url('loyalty/storeTier')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $tier['id'] ?>"><input type="hidden" name="slug" value="<?= e($tier['slug']) ?>"><label>عنوان سطح<input name="title" value="<?= e($tier['title']) ?>" required></label><label>عنوان مدال<input name="medal_title" value="<?= e($tier['medal_title']) ?>" required></label><label>حداقل امتیاز<input name="min_points" type="number" min="1" value="<?= (int) $tier['min_points'] ?>" required></label><label>رنگ<input name="color" type="color" value="<?= e($tier['color']) ?>"></label><label>آیکن<input name="icon_key" value="<?= e($tier['icon_key']) ?>"></label><label>ترتیب<input name="sort_order" type="number" value="<?= (int) $tier['sort_order'] ?>"></label><label class="full">توضیح مدال<input name="description" value="<?= e($tier['description'] ?? '') ?>"></label><label><input type="checkbox" name="is_active" value="1"<?= checked((int) $tier['is_active'], 1) ?>> سطح فعال باشد</label><div class="full"><button class="btn small" type="submit">ذخیره سطح</button></div></form><form class="mt-2" method="post" action="<?= e(url('loyalty/toggleTier/' . (int) $tier['id'])) ?>"><?= csrf_field() ?><button class="btn small <?= !empty($tier['is_active']) ? 'secondary' : 'success' ?>" type="submit"><?= !empty($tier['is_active']) ? 'غیرفعال کردن سطح' : 'فعال کردن سطح' ?></button></form></details></td></tr>
    <?php endforeach; ?>
    <?php if (!$tiers): ?><tr><td colspan="6" class="empty">سطحی تعریف نشده است.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <div class="card-body"><h3>افزودن سطح</h3><form class="form-grid three" method="post" action="<?= e(url('loyalty/storeTier')) ?>">
    <?= csrf_field() ?>
    <label>شناسه یکتا<input name="slug" pattern="[A-Za-z0-9_-]+" required placeholder="emerald"></label><label>عنوان سطح<input name="title" required placeholder="زمردی"></label><label>عنوان مدال<input name="medal_title" required placeholder="مدال زمرد"></label><label>حداقل امتیاز<input name="min_points" type="number" min="1" required></label><label>رنگ مدال<input name="color" type="color" value="#0f766e"></label><label>نام آیکن<input name="icon_key" value="award"></label><label>ترتیب نمایش<input name="sort_order" type="number" value="100"></label><label class="full">توضیح<input name="description" maxlength="255"></label><div class="full"><button class="btn" type="submit">افزودن سطح و مدال</button></div>
  </form></div>
</section>
