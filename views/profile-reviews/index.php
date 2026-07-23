<?php
$filters = $filters ?? [];
$summary = $summary ?? [];
$fieldLabels = ProfileRequest::fieldLabels();
$pageUrl = static function ($page) use ($filters) {
    $query = array_filter([
        'status' => $filters['status'] ?? '',
        'role' => $filters['role'] ?? '',
        'q' => $filters['q'] ?? '',
        'user_id' => $filters['user_id'] ?? 0,
        'page' => $page,
    ], static function ($value) {
        return $value !== '' && $value !== 0 && $value !== null;
    });
    return url('profile-reviews', $query);
};
?>

<section class="proma-section-header proma-page-header proma-profile-review-hero">
  <div>
    <span class="proma-page-eyebrow">کنترل تغییرات حساب</span>
    <h1>تأیید اصلاح مشخصات</h1>
    <p>درخواست‌های اصلاح اطلاعات هویتی و تماس را مقایسه، به‌صورت جزئی تأیید و در تاریخچه هر حساب پیگیری کنید.</p>
  </div>
  <a class="btn secondary" href="<?= e(url('profile-reviews', ['status' => 'pending'])) ?>"><?= proma_icon('history') ?><span>درخواست‌های باز</span></a>
</section>

<section class="proma-review-summary" aria-label="خلاصه درخواست‌ها">
  <a href="<?= e(url('profile-reviews', ['status' => 'pending'])) ?>"><span><?= proma_icon('history') ?></span><small>در انتظار بررسی</small><strong><?= to_persian_digits($summary['pending'] ?? 0) ?></strong></a>
  <a href="<?= e(url('profile-reviews', ['status' => 'approved'])) ?>"><span><?= proma_icon('check') ?></span><small>تأیید کامل</small><strong><?= to_persian_digits($summary['approved'] ?? 0) ?></strong></a>
  <a href="<?= e(url('profile-reviews', ['status' => 'partial'])) ?>"><span><?= proma_icon('edit') ?></span><small>تأیید جزئی</small><strong><?= to_persian_digits($summary['partial'] ?? 0) ?></strong></a>
  <a href="<?= e(url('profile-reviews', ['status' => 'rejected'])) ?>"><span><?= proma_icon('slash') ?></span><small>رد شده</small><strong><?= to_persian_digits($summary['rejected'] ?? 0) ?></strong></a>
</section>

<section class="card proma-profile-review-workspace">
  <div class="card-header card-no-border">
    <div><h2>فهرست درخواست‌ها</h2><p><?= to_persian_digits($pagination['total'] ?? 0) ?> رویداد مطابق فیلتر فعلی</p></div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('profile-reviews')) ?>" class="proma-review-filter form-grid four">
      <label>جست‌وجوی حساب<input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="نام، کد ملی، موبایل یا نام کاربری"></label>
      <label>نقش<select name="role"><option value="">همه نقش‌ها</option><?php foreach (['admin', 'operator', 'lawyer', 'customer'] as $role): ?><option value="<?= e($role) ?>"<?= selected($filters['role'] ?? '', $role) ?>><?= e(role_label($role)) ?></option><?php endforeach; ?></select></label>
      <label>وضعیت<select name="status"><option value="">همه وضعیت‌ها</option><?php foreach (['pending', 'approved', 'partial', 'rejected'] as $status): ?><option value="<?= e($status) ?>"<?= selected($filters['status'] ?? '', $status) ?>><?= e(ProfileRequest::statusLabel($status)) ?></option><?php endforeach; ?></select></label>
      <?php if (!empty($filters['user_id'])): ?><input type="hidden" name="user_id" value="<?= (int) $filters['user_id'] ?>"><?php endif; ?>
      <div class="proma-field-actions"><button class="btn" type="submit"><?= proma_icon('eye') ?><span>اعمال فیلتر</span></button><a class="btn secondary" href="<?= e(url('profile-reviews')) ?>"><?= proma_icon('close') ?><span>پاک کردن</span></a></div>
    </form>
  </div>
</section>

<section class="proma-profile-review-list">
  <?php foreach (($requests ?? []) as $request): ?>
    <?php
      $requested = $request['requested_fields'] ?? [];
      $conflicts = $request['conflict_fields'] ?? [];
      $approved = $request['reviewed_fields'] ?? [];
      $rejected = $request['rejected_fields'] ?? [];
      $isPending = ($request['status'] ?? '') === 'pending';
    ?>
    <article class="proma-review-request proma-review-request--<?= e($request['status'] ?? 'pending') ?>">
      <header>
        <div class="proma-review-person">
          <?php $reviewAvatar = avatar_key_for($request['avatar_key'] ?? null, $request['user_id'] ?? ($request['full_name'] ?? '')); ?>
          <span class="proma-avatar-choice <?= e($reviewAvatar) ?>" aria-label="<?= e($request['full_name'] ?? '') ?>"><img data-avatar-image src="<?= e(user_avatar_asset_url($request)) ?>" alt="آواتار <?= e($request['full_name'] ?? '') ?>" loading="lazy"></span>
          <div><strong><?= e($request['full_name'] ?? '') ?></strong><small><?= e(role_label($request['role'] ?? '')) ?> · <?= e(to_persian_digits($request['mobile'] ?? '')) ?></small><small dir="ltr"><?= e($request['username'] ?: $request['national_id'] ?: '-') ?></small></div>
        </div>
        <div class="proma-review-meta"><span class="badge <?= e(badge_class($request['status'] ?? 'pending')) ?>"><?= e(ProfileRequest::statusLabel($request['status'] ?? 'pending')) ?></span><time><?= e(jdatetime($request['created_at'] ?? '')) ?></time><a href="<?= e(url('profile-reviews', ['status' => '', 'user_id' => (int) $request['user_id']])) ?>">تاریخچه این حساب</a></div>
      </header>

      <form method="post" action="<?= e(url('profile/approve/' . (int) $request['id'])) ?>" class="proma-review-fields">
        <?= csrf_field() ?>
        <input type="hidden" name="selection_submitted" value="1">
        <?php foreach ($requested as $field => $value): ?>
          <?php $hasConflict = in_array($field, $conflicts, true); ?>
          <label class="proma-review-field <?= $hasConflict ? 'has-conflict' : '' ?>">
            <?php if ($isPending): ?><input type="checkbox" name="approved_fields[]" value="<?= e($field) ?>"<?= $hasConflict ? ' disabled' : ' checked' ?>><?php else: ?><span class="proma-review-field-state"><?= in_array($field, $approved, true) ? proma_icon('check') : proma_icon('close') ?></span><?php endif; ?>
            <span><small><?= e($fieldLabels[$field] ?? $field) ?></small><del><?= e($request['current_' . $field] ?? '') ?></del><strong><?= e($value) ?></strong></span>
            <?php if ($hasConflict): ?><em>مقدار حساب پس از ثبت درخواست تغییر کرده و این فیلد خودکار قفل شده است.</em><?php elseif (!$isPending && in_array($field, $rejected, true)): ?><em>این فیلد تأیید نشده است.</em><?php endif; ?>
          </label>
        <?php endforeach; ?>

        <?php if ($isPending): ?>
          <label class="full">یادداشت بررسی<textarea name="review_notes" rows="2" placeholder="توضیح تصمیم برای کاربر و تاریخچه مدیریت"></textarea></label>
          <div class="proma-review-actions">
            <button class="btn" type="submit"><?= proma_icon('check') ?><span>ثبت نتیجه انتخاب‌ها</span></button>
            <button class="btn danger" type="submit" formaction="<?= e(url('profile/reject/' . (int) $request['id'])) ?>"><?= proma_icon('close') ?><span>رد کامل درخواست</span></button>
          </div>
        <?php else: ?>
          <div class="proma-review-audit full">
            <span><small>بررسی‌کننده</small><strong><?= e($request['reviewer_name'] ?: 'مدیریت') ?></strong></span>
            <span><small>آخرین تصمیم</small><strong><?= e(jdatetime($request['updated_at'] ?: $request['created_at'])) ?></strong></span>
            <?php if (!empty($request['review_notes'])): ?><p><strong>یادداشت مدیریت:</strong> <?= e($request['review_notes']) ?></p><?php endif; ?>
            <?php if (!empty($request['customer_response'])): ?><p><strong>پاسخ کاربر:</strong> <?= e($request['customer_response']) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
      </form>
    </article>
  <?php endforeach; ?>
  <?php if (empty($requests)): ?><div class="card"><div class="empty">درخواستی با این فیلتر پیدا نشد.</div></div><?php endif; ?>
</section>

<?= render_pagination($pagination ?? [], $pageUrl) ?>
