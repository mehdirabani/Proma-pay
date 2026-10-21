<?php
$profile = $loyaltyProfile ?? ['points' => 0, 'tier' => null, 'next_tier' => null, 'progress' => 0, 'medals' => [], 'history' => []];
$loyaltyRules = $loyaltyRules ?? [];
$pointTypeLabel = static function ($type) {
    return ['system' => 'خودکار', 'manual' => 'ثبت مدیر', 'reversal' => 'برگشت امتیاز'][$type] ?? $type;
};
?>
<div class="proma-customer-360__overview-grid">
  <section class="card"><div class="card-header"><div><h2>امتیاز و سطح فعلی</h2><p class="text-muted">امتیاز خالص از دفترکل فعالیت‌های مشتری</p></div></div><div class="card-body">
    <div class="proma-customer-card-metrics"><span><strong><?= to_persian_digits($profile['points'] ?? 0) ?></strong><small>امتیاز</small></span><span><strong><?= e($profile['tier']['title'] ?? 'تازه‌وارد') ?></strong><small>سطح فعلی</small></span><span><strong><?= to_persian_digits($profile['progress'] ?? 0) ?>٪</strong><small>مسیر تا سطح بعد</small></span></div>
    <?php if (!empty($profile['next_tier'])): ?><p class="mt-3">برای رسیدن به سطح <?= e($profile['next_tier']['title']) ?>، <?= to_persian_digits(max(0, (int) $profile['next_tier']['min_points'] - (int) $profile['points'])) ?> امتیاز دیگر لازم است.</p><progress max="100" value="<?= max(0, min(100, (int) ($profile['progress'] ?? 0))) ?>" aria-label="پیشرفت تا سطح بعد"></progress><?php elseif (!empty($profile['tier'])): ?><p class="mt-3">مشتری به بالاترین سطح فعال باشگاه رسیده است.</p><?php else: ?><p class="mt-3">با انجام فعالیت‌های امتیازی، مسیر سطح‌بندی مشتری آغاز می‌شود.</p><?php endif; ?>
  </div></section>
  <section class="card"><div class="card-header"><div><h2>مدال‌های سطحی</h2><p class="text-muted">هر مدال با عبور از حد امتیاز سطح آن ثبت شده است.</p></div></div><div class="card-body"><div class="proma-medal-row">
    <?php foreach (($profile['medals'] ?? []) as $medal): ?><span class="badge badge-light-warning proma-medal-badge" title="<?= e($medal['description'] ?? '') ?>"><i data-feather="<?= e($medal['icon_key'] ?: 'award') ?>"></i><?= e($medal['medal_title'] ?? $medal['title']) ?> · <?= to_persian_digits($medal['points'] ?? 0) ?> امتیاز</span><?php endforeach; ?>
    <?php if (empty($profile['medals'])): ?><span class="badge muted">هنوز مدالی دریافت نشده است</span><?php endif; ?>
  </div></div></section>
</div>

<section class="card mt-3"><div class="card-header card-no-border"><div><h2>ثبت امتیاز فعالیت</h2><p class="text-muted">فقط فعالیتی را انتخاب کنید که انجام آن قابل تأیید است؛ شرح فعالیت در سابقه ثبت می‌شود.</p></div></div><div class="card-body">
  <?php if ($loyaltyRules): ?><form class="form-grid two" method="post" action="<?= e(url('customers/awardPoints/' . (int) $customer['id'])) ?>">
    <?= csrf_field() ?><label>فعالیت<select name="rule_id" required><option value="">انتخاب کنید</option><?php foreach ($loyaltyRules as $rule): ?><option value="<?= (int) $rule['id'] ?>"><?= e($rule['title']) ?> · <?= to_persian_digits($rule['points']) ?> امتیاز</option><?php endforeach; ?></select></label><label class="full">شرح انجام فعالیت<textarea name="note" rows="3" maxlength="2000" required placeholder="جزئیات کوتاه و قابل پیگیری فعالیت"></textarea></label><div class="full"><button class="btn" type="submit">ثبت امتیاز برای مشتری</button></div>
  </form><?php else: ?><div class="notice info">برای ثبت دستی امتیاز، ابتدا از صفحهٔ باشگاه امتیاز مشتریان یک فعالیت دستی فعال تعریف کنید.</div><?php endif; ?>
</div></section>

<section class="card mt-3"><div class="card-header card-no-border"><div><h2>دفترکل امتیاز</h2><p class="text-muted">امتیازهای دریافتی و اصلاحیه‌ها با زمان و مسئول ثبت نگهداری می‌شوند.</p></div></div><div class="table-wrap"><table><thead><tr><th>فعالیت</th><th>نوع ثبت</th><th>شرح</th><th>امتیاز</th><th>ثبت‌کننده</th><th>زمان</th></tr></thead><tbody>
<?php foreach (($profile['history'] ?? []) as $entry): ?><tr><td><?= e($entry['activity_title']) ?></td><td><?= e($pointTypeLabel($entry['source_type'] ?? '')) ?></td><td><?= e($entry['note'] ?? '—') ?></td><td class="<?= (int) $entry['points'] < 0 ? 'text-danger' : 'text-success' ?>"><?= (int) $entry['points'] > 0 ? '+' : '' ?><?= to_persian_digits($entry['points']) ?></td><td><?= e($entry['actor_name'] ?? (($entry['source_type'] ?? '') === 'system' ? 'سامانه' : '—')) ?></td><td><?= e(jdatetime($entry['created_at'] ?? '')) ?></td></tr><?php endforeach; ?>
<?php if (empty($profile['history'])): ?><tr><td colspan="6" class="empty">هنوز امتیازی برای این مشتری ثبت نشده است.</td></tr><?php endif; ?>
</tbody></table></div></section>
