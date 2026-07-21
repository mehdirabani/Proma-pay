<?php
$summary = $summary ?? [];
$definitions = $definitions ?? [];
$history = $history ?? [];
$filter = $filter ?? '';
$criteriaOptions = [
    'contract_count' => 'تعداد قرارداد غیرلغوشده',
    'payment_count' => 'تعداد پرداخت موفق',
    'on_time_count' => 'پرداخت به موقع',
    'early_payment_count' => 'پرداخت زودهنگام',
    'completed_contract_count' => 'قرارداد تسویه شده',
    'overdue_count' => 'اقساط معوق',
    'early_settlement_count' => 'تسویه زودهنگام',
];
$behaviorLabels = ['permanent' => 'دائمی', 'reversible' => 'برگشت پذیر', 'manual' => 'دستی'];
$filterUrl = static function ($value) { return url('medals', $value ? ['filter' => $value] : []); };
$definitionForm = static function ($definition = []) use ($criteriaOptions) {
    $criteria = json_decode((string) ($definition['criteria_json'] ?? ''), true) ?: [];
    $color = sanitize_hex_color($definition['color'] ?? '', '#7366ff');
    ?>
    <div class="modal-body proma-medal-form">
      <?= csrf_field() ?>
      <section class="proma-medal-form-section">
        <header><span><?= proma_icon('award') ?></span><div><h4>هویت مدال</h4><p>نام و جایگاه مدال در سامانه</p></div></header>
        <div class="form-grid two">
          <label><span>عنوان مدال <b class="form-required">*</b></span><input name="title" required value="<?= e($definition['title'] ?? '') ?>"></label>
          <label><span>شناسه انگلیسی <b class="form-required">*</b></span><input name="slug" required pattern="[A-Za-z0-9_-]+" value="<?= e($definition['slug'] ?? '') ?>" placeholder="first-contract" dir="ltr"></label>
          <label>دسته بندی<select name="category"><option value="payment"<?= selected($definition['category'] ?? '', 'payment') ?>>پرداخت</option><option value="loyalty"<?= selected($definition['category'] ?? '', 'loyalty') ?>>وفاداری</option><option value="early_payment"<?= selected($definition['category'] ?? '', 'early_payment') ?>>پرداخت زودهنگام</option><option value="settlement"<?= selected($definition['category'] ?? '', 'settlement') ?>>تسویه</option><option value="contract"<?= selected($definition['category'] ?? '', 'contract') ?>>قرارداد</option><option value="activity"<?= selected($definition['category'] ?? 'activity', 'activity') ?>>فعالیت</option><option value="special"<?= selected($definition['category'] ?? '', 'special') ?>>ویژه</option><option value="manual"<?= selected($definition['category'] ?? '', 'manual') ?>>دستی</option></select></label>
          <label>امتیاز<input name="points" inputmode="numeric" value="<?= e($definition['points'] ?? 0) ?>"></label>
        </div>
      </section>

      <section class="proma-medal-form-section">
        <header><span><?= proma_icon('edit') ?></span><div><h4>نمایش</h4><p>رنگ، آیکن و ترتیب کارت مدال</p></div></header>
        <div class="form-grid two">
          <label>آیکن داخلی<select name="icon_key"><option value="award"<?= selected($definition['icon_key'] ?? 'award', 'award') ?>>مدال</option><option value="check"<?= selected($definition['icon_key'] ?? '', 'check') ?>>تایید</option><option value="chart"<?= selected($definition['icon_key'] ?? '', 'chart') ?>>نمودار</option><option value="book"<?= selected($definition['icon_key'] ?? '', 'book') ?>>دفترچه</option></select></label>
          <label>ترتیب نمایش<input name="sort_order" inputmode="numeric" value="<?= e($definition['sort_order'] ?? 0) ?>"></label>
          <label class="full">رنگ مدال<span class="proma-medal-color-field" data-medal-color-field><input type="color" value="<?= e($color) ?>" data-medal-color-picker aria-label="انتخاب رنگ"><input name="color" value="<?= e($color) ?>" dir="ltr" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-medal-color-text><button class="proma-icon-button" type="button" data-medal-color-reset title="بازنشانی رنگ" aria-label="بازنشانی رنگ"><?= proma_icon('history') ?></button><i data-medal-color-preview style="--medal-color: <?= e($color) ?>"><?= proma_icon('award') ?></i></span></label>
        </div>
      </section>

      <section class="proma-medal-form-section">
        <header><span><?= proma_icon('history') ?></span><div><h4>رفتار اعطا و لغو</h4><p>چرخه عمر مدال را شفاف تعیین کنید</p></div></header>
        <div class="form-grid two">
          <label>نوع اعطا<select name="award_type"><option value="automatic"<?= selected($definition['award_type'] ?? 'automatic', 'automatic') ?>>خودکار</option><option value="manual"<?= selected($definition['award_type'] ?? '', 'manual') ?>>دستی</option></select></label>
          <label>نوع رفتار<select name="behavior_type"><option value="permanent"<?= selected($definition['behavior_type'] ?? 'permanent', 'permanent') ?>>دائمی</option><option value="reversible"<?= selected($definition['behavior_type'] ?? '', 'reversible') ?>>برگشت پذیر</option><option value="manual"<?= selected($definition['behavior_type'] ?? '', 'manual') ?>>مدیریت دستی</option></select></label>
          <label>لغو پس از نقض معیار<select name="revocation_behavior"><option value="never"<?= selected($definition['revocation_behavior'] ?? 'never', 'never') ?>>لغو نشود</option><option value="automatic"<?= selected($definition['revocation_behavior'] ?? '', 'automatic') ?>>خودکار لغو شود</option></select></label>
          <label>برقراری دوباره معیار<select name="reactivation_behavior"><option value="restore"<?= selected($definition['reactivation_behavior'] ?? 'restore', 'restore') ?>>همان سابقه بازگردد</option><option value="new"<?= selected($definition['reactivation_behavior'] ?? '', 'new') ?>>مدال جدید ساخته شود</option></select></label>
        </div>
      </section>

      <section class="proma-medal-form-section">
        <header><span><?= proma_icon('chart') ?></span><div><h4>معیار خودکار</h4><p>حداقل یا حداکثر لازم برای دریافت مدال</p></div></header>
        <div class="form-grid two">
          <label class="full">نوع معیار<select name="criteria_type"><option value="">بدون معیار خودکار</option><?php foreach ($criteriaOptions as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($definition['criteria_type'] ?? '', $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>حداقل معیار<input name="criteria_minimum" inputmode="numeric" value="<?= e($criteria['minimum'] ?? '') ?>" placeholder="مثلا ۵"></label>
          <label>حداکثر معیار<input name="criteria_maximum" inputmode="numeric" value="<?= e($criteria['maximum'] ?? '') ?>" placeholder="اختیاری"></label>
        </div>
      </section>

      <section class="proma-medal-form-section">
        <header><span><?= proma_icon('book') ?></span><div><h4>متن و راهنما</h4><p>متنی که مدیر و مشتری مشاهده می کنند</p></div></header>
        <div class="form-grid two">
          <label class="full">توضیح کوتاه<input name="short_description" value="<?= e($definition['short_description'] ?? '') ?>" maxlength="255"></label>
          <label class="full">توضیح کامل<textarea name="full_description" rows="3"><?= e($definition['full_description'] ?? '') ?></textarea></label>
          <label class="full">روش دریافت<textarea name="how_to_earn" rows="3"><?= e($definition['how_to_earn'] ?? '') ?></textarea></label>
        </div>
      </section>

      <section class="proma-medal-form-section">
        <header><span><?= proma_icon('check') ?></span><div><h4>وضعیت</h4><p>فعال بودن و امکان اعطای تکراری</p></div></header>
        <div class="proma-medal-checks"><label class="proma-confirm-check"><input type="checkbox" name="is_repeatable" value="1"<?= !empty($definition['is_repeatable']) ? ' checked' : '' ?>> امکان اعطای تکراری دارد</label><label class="proma-confirm-check"><input type="checkbox" name="is_active" value="1"<?= empty($definition) || !empty($definition['is_active']) ? ' checked' : '' ?>> مدال فعال است</label></div>
      </section>
    </div>
    <?php
};
?>

<section class="proma-page-hero">
  <div><span class="proma-page-kicker"><?= proma_icon('award') ?> وفاداری مشتری</span><h2>مدیریت مدال ها</h2><p>تعریف، ارزیابی و تاریخچه مدال ها بدون حذف سوابق مشتری مدیریت می شود.</p></div>
  <div class="proma-page-hero__actions"><button class="btn" type="button" data-open-modal="medal-create"><?= proma_icon('award') ?><span>افزودن مدال</span></button></div>
</section>

<section class="proma-medal-stats" aria-label="خلاصه مدال ها">
  <article><span><?= proma_icon('award') ?></span><div><small>تعریف فعال</small><strong><?= to_persian_digits($summary['active'] ?? 0) ?></strong></div></article>
  <article><span><?= proma_icon('history') ?></span><div><small>خودکار</small><strong><?= to_persian_digits($summary['automatic'] ?? 0) ?></strong></div></article>
  <article><span><?= proma_icon('user') ?></span><div><small>اعطاشده فعال</small><strong><?= to_persian_digits($summary['awarded'] ?? 0) ?></strong></div></article>
  <article><span><?= proma_icon('slash') ?></span><div><small>لغوشده</small><strong><?= to_persian_digits($summary['revoked'] ?? 0) ?></strong></div></article>
  <article><span><?= proma_icon('history') ?></span><div><small>برگشت پذیر</small><strong><?= to_persian_digits($summary['reversible'] ?? 0) ?></strong></div></article>
  <article><span><?= proma_icon('clock') ?></span><div><small>در انتظار ارزیابی</small><strong><?= to_persian_digits($summary['pending_reconciliation'] ?? 0) ?></strong></div></article>
</section>

<section class="card proma-medal-sync-card"><div class="card-body"><div><h3>بازبینی و همگام‌سازی مدال‌ها</h3><p>معیارها برای حداکثر ۱۰۰ مشتری فعال ارزیابی می‌شوند؛ اطلاعات مالی و قرارداد تغییر نمی‌کند.</p></div><form method="post" action="<?= e(url('medals/synchronize')) ?>" class="proma-medal-sync-form"><?= csrf_field() ?><input name="customer_id" inputmode="numeric" placeholder="شناسه مشتری برای بازبینی تکی"><button class="btn secondary" type="submit"><?= proma_icon('history') ?><span>همگام‌سازی</span></button></form></div></section>

<nav class="proma-medal-tabs" aria-label="فیلتر مدال ها">
  <?php foreach (['' => ['همه', $summary['total'] ?? 0], 'automatic' => ['خودکار', $summary['automatic'] ?? 0], 'manual' => ['دستی', $summary['manual'] ?? 0], 'reversible' => ['برگشت پذیر', $summary['reversible'] ?? 0], 'permanent' => ['دائمی', max(0, (int) ($summary['total'] ?? 0) - (int) ($summary['reversible'] ?? 0) - (int) ($summary['manual'] ?? 0))], 'inactive' => ['غیرفعال', max(0, (int) ($summary['total'] ?? 0) - (int) ($summary['active'] ?? 0))]] as $value => $tab): ?><a class="<?= $filter === $value ? 'active' : '' ?>" href="<?= e($filterUrl($value)) ?>"><?= e($tab[0]) ?> <span><?= to_persian_digits($tab[1]) ?></span></a><?php endforeach; ?>
</nav>

<section class="proma-medal-grid">
  <?php foreach ($definitions as $definition): $criteria = json_decode((string) ($definition['criteria_json'] ?? ''), true) ?: []; ?>
    <article class="proma-medal-card <?= empty($definition['is_active']) ? 'is-inactive' : '' ?>">
      <header><span class="proma-medal-card__icon" style="--medal-color: <?= e(sanitize_hex_color($definition['color'] ?? '', '#7366ff')) ?>"><?= proma_icon($definition['icon_key'] ?? 'award') ?></span><div><h3><?= e($definition['title']) ?></h3><small dir="ltr"><?= e($definition['slug']) ?></small></div><span class="badge <?= !empty($definition['is_active']) ? 'success' : 'muted' ?>"><?= !empty($definition['is_active']) ? 'فعال' : 'غیرفعال' ?></span></header>
      <p><?= e($definition['short_description'] ?: ($definition['how_to_earn'] ?: 'توضیحی ثبت نشده است.')) ?></p>
      <dl><div><dt>اعطا</dt><dd><?= ($definition['award_type'] ?? 'automatic') === 'manual' ? 'دستی' : 'خودکار' ?></dd></div><div><dt>رفتار</dt><dd><?= e($behaviorLabels[$definition['behavior_type'] ?? 'permanent'] ?? 'دائمی') ?></dd></div><div><dt>امتیاز</dt><dd><?= to_persian_digits($definition['points']) ?></dd></div><div><dt>دارنده فعال</dt><dd><?= to_persian_digits($definition['active_holders'] ?? 0) ?></dd></div><div><dt>لغوشده</dt><dd><?= to_persian_digits($definition['revoked_holders'] ?? 0) ?></dd></div><div><dt>آخرین ارزیابی</dt><dd><?= !empty($definition['last_evaluated_at']) ? e(jdatetime($definition['last_evaluated_at'])) : 'انجام نشده' ?></dd></div></dl>
      <footer><span class="badge info"><?= e($criteriaOptions[$definition['criteria_type']] ?? 'بدون معیار خودکار') ?><?= isset($criteria['minimum']) ? ' ≥ ' . to_persian_digits($criteria['minimum']) : '' ?><?= isset($criteria['maximum']) ? ' ≤ ' . to_persian_digits($criteria['maximum']) : '' ?></span><div class="proma-table-actions"><button class="proma-icon-button" type="button" data-open-modal="medal-edit-<?= (int) $definition['id'] ?>" title="ویرایش مدال" aria-label="ویرایش <?= e($definition['title']) ?>"><?= proma_icon('edit') ?></button><form method="post" action="<?= e(url('medals/toggle/' . $definition['id'])) ?>"><?= csrf_field() ?><button class="proma-icon-button" type="submit" title="<?= !empty($definition['is_active']) ? 'غیرفعال کردن' : 'فعال کردن' ?>" aria-label="تغییر وضعیت <?= e($definition['title']) ?>"><?= !empty($definition['is_active']) ? proma_icon('slash') : proma_icon('check') ?></button></form></div></footer>
    </article>
  <?php endforeach; ?>
  <?php if (!$definitions): ?><div class="empty">مدالی مطابق فیلتر انتخاب شده وجود ندارد.</div><?php endif; ?>
</section>

<section class="card proma-medal-history"><div class="card-header"><div><h3>تاریخچه اخیر</h3><p>اعطا، لغو و بازگردانی مدال ها قابل پیگیری است.</p></div></div><div class="table-wrap"><table><thead><tr><th>کاربر</th><th>مدال</th><th>عملیات</th><th>توسط</th><th>زمان</th></tr></thead><tbody><?php foreach ($history as $item): ?><tr><td><?= e($item['user_name']) ?></td><td><?= e($item['medal_title']) ?></td><td><span class="badge <?= $item['action'] === 'revoked' ? 'danger' : ($item['action'] === 'restored' ? 'success' : 'info') ?>"><?= e($item['action'] === 'awarded' ? 'اعطا' : ($item['action'] === 'revoked' ? 'لغو' : 'بازگردانی')) ?></span></td><td><?= e($item['actor_name'] ?: 'سیستم') ?></td><td><?= e(jdatetime($item['created_at'])) ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="5" class="empty">هنوز رویدادی ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div></section>

<div class="modal" id="medal-create"><div class="modal-content proma-medal-modal"><div class="modal-header"><h3><?= proma_icon('award') ?> افزودن مدال</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button></div><form method="post" action="<?= e(url('medals/store')) ?>"><?php $definitionForm([]); ?><div class="modal-footer"><button class="btn" type="submit">ذخیره مدال</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div>
<?php foreach ($definitions as $definition): ?><div class="modal" id="medal-edit-<?= (int) $definition['id'] ?>"><div class="modal-content proma-medal-modal"><div class="modal-header"><h3><?= proma_icon('edit') ?> ویرایش مدال</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button></div><form method="post" action="<?= e(url('medals/store')) ?>"><?php $definitionForm($definition); ?><div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div><?php endforeach; ?>
