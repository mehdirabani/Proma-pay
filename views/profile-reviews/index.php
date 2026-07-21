<?php
$filters = $filters ?? [];
$pagination = $pagination ?? ['total' => count($requests ?? []), 'page' => 1, 'pages' => 1, 'per_page' => 24];
$pageUrl = static function ($page) use ($filters) {
    return url('profile-reviews', array_filter([
        'q' => $filters['q'] ?? '',
        'role' => $filters['role'] ?? '',
        'status' => $filters['status'] ?? '',
        'user_id' => $filters['user_id'] ?? '',
        'page' => (int) $page > 1 ? (int) $page : null,
    ], static fn($value) => $value !== '' && $value !== null));
};
$fieldLabels = ProfileRequest::fieldLabels();
?>
<section class="proma-page-hero proma-review-hero">
  <div>
    <span class="proma-page-kicker"><?= proma_icon('check') ?> کنترل تغییرات حساب</span>
    <h2>تأیید اصلاح مشخصات</h2>
    <p>درخواست‌های کاربران و مشتریان را فیلدبه‌فیلد بررسی کنید و تاریخچه تصمیم‌ها را برای هر حساب ببینید.</p>
  </div>
  <a class="btn secondary" href="<?= e(url('profile-reviews', ['status' => 'pending'])) ?>"><?= proma_icon('history') ?><span>درخواست‌های باز</span></a>
</section>

<section class="proma-review-summary" aria-label="خلاصه درخواست‌ها">
  <?php foreach ([
      ['pending', 'در انتظار بررسی', 'clock'],
      ['approved', 'تأیید کامل', 'check'],
      ['partial', 'تأیید جزئی', 'edit'],
      ['rejected', 'رد شده', 'slash'],
  ] as $item): ?>
    <a href="<?= e(url('profile-reviews', ['status' => $item[0]])) ?>" class="proma-review-summary__item <?= ($filters['status'] ?? '') === $item[0] ? 'is-active' : '' ?>">
      <span><?= proma_icon($item[2]) ?></span>
      <small><?= e($item[1]) ?></small>
      <strong><?= to_persian_digits($summary[$item[0]] ?? 0) ?></strong>
    </a>
  <?php endforeach; ?>
</section>

<section class="card proma-review-filter-card">
  <div class="card-body">
    <form method="get" action="<?= e(url('profile-reviews')) ?>" class="form-grid four proma-review-filter">
      <input type="hidden" name="route" value="profile-reviews">
      <label>جستجوی حساب<input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="نام، نام کاربری، موبایل یا کد ملی"></label>
      <label>نقش
        <select name="role">
          <option value="">همه نقش‌ها</option>
          <?php foreach (['admin', 'operator', 'lawyer', 'customer'] as $role): ?><option value="<?= e($role) ?>"<?= selected($filters['role'] ?? '', $role) ?>><?= e(role_label($role)) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>وضعیت
        <select name="status">
          <option value="">همه وضعیت‌ها</option>
          <?php foreach (['pending', 'approved', 'partial', 'rejected'] as $status): ?><option value="<?= e($status) ?>"<?= selected($filters['status'] ?? 'pending', $status) ?>><?= e(ProfileRequest::statusLabel($status)) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="actions"><button class="btn secondary" type="submit"><?= proma_icon('filter') ?><span>اعمال فیلتر</span></button><a class="btn ghost" href="<?= e(url('profile-reviews')) ?>">پاک‌کردن</a></div>
    </form>
  </div>
</section>

<div class="proma-review-request-grid">
<?php foreach (($requests ?? []) as $request): ?>
  <?php
    $requested = $request['requested_fields'] ?? [];
    $conflicts = $request['conflict_fields'] ?? [];
    $isPending = ($request['status'] ?? '') === 'pending';
    $userHistory = $histories[(int) $request['user_id']] ?? [];
  ?>
  <article class="card proma-review-account-card">
    <header class="proma-review-account-card__header">
      <div>
        <strong><?= e($request['full_name'] ?? '') ?></strong>
        <small><?= e(role_label($request['role'] ?? '')) ?> · <?= e(to_persian_digits($request['mobile'] ?? '')) ?> · <?= e($request['username'] ?? $request['national_id'] ?? '') ?></small>
      </div>
      <span class="badge <?= e(badge_class($request['status'] ?? 'pending')) ?>"><?= e(ProfileRequest::statusLabel($request['status'] ?? 'pending')) ?></span>
    </header>

    <form method="post" action="<?= e(url('profile/approve/' . (int) $request['id'])) ?>" class="proma-review-fields" data-disable-on-submit>
      <?= csrf_field() ?>
      <?php foreach ($requested as $field => $value): ?>
        <?php $hasConflict = in_array($field, $conflicts, true); ?>
        <label class="proma-review-field <?= $hasConflict ? 'has-conflict' : '' ?>">
          <?php if ($isPending): ?><input type="checkbox" name="approved_fields[]" value="<?= e($field) ?>"<?= $hasConflict ? ' disabled' : ' checked' ?>><?php endif; ?>
          <span>
            <small><?= e($fieldLabels[$field] ?? $field) ?></small>
            <del title="مقدار فعلی"><?= e($request['current_' . $field] ?? '') ?></del>
            <strong title="مقدار درخواستی"><?= e($value) ?></strong>
            <?php if ($hasConflict): ?><em>مقدار فعلی بعد از ثبت درخواست تغییر کرده و این فیلد قابل اعمال خودکار نیست.</em><?php endif; ?>
          </span>
        </label>
      <?php endforeach; ?>
      <?php if ($isPending): ?>
        <label class="full">یادداشت بررسی<textarea name="review_notes" rows="2" placeholder="دلیل تصمیم یا توضیح برای کاربر"></textarea></label>
        <div class="proma-review-actions"><button class="btn success" type="submit"><?= proma_icon('check') ?><span>ثبت فیلدهای تأییدشده</span></button></div>
      <?php elseif (!empty($request['review_notes'])): ?>
        <p class="notice info full"><?= e($request['review_notes']) ?></p>
      <?php endif; ?>
    </form>

    <?php if ($isPending): ?>
      <form method="post" action="<?= e(url('profile/reject/' . (int) $request['id'])) ?>" class="proma-review-reject" data-disable-on-submit>
        <?= csrf_field() ?>
        <input name="review_notes" required minlength="3" placeholder="علت رد کامل درخواست">
        <button class="btn danger" type="submit"><?= proma_icon('slash') ?><span>رد کامل</span></button>
      </form>
    <?php endif; ?>

    <details class="proma-review-history">
      <summary><?= proma_icon('history') ?><span>لاگ این کاربر</span><b><?= to_persian_digits(count($userHistory)) ?></b></summary>
      <div class="proma-review-history__list">
        <?php foreach ($userHistory as $history): ?>
          <div>
            <span class="badge <?= e(badge_class($history['status'] ?? '')) ?>"><?= e(ProfileRequest::statusLabel($history['status'] ?? '')) ?></span>
            <strong><?= e(jdatetime($history['created_at'] ?? '')) ?></strong>
            <small><?= !empty($history['reviewer_name']) ? 'بررسی توسط ' . e($history['reviewer_name']) : 'در انتظار بررسی مدیریت' ?></small>
            <?php if (!empty($history['review_notes'])): ?><p><?= e($history['review_notes']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  </article>
<?php endforeach; ?>
<?php if (empty($requests)): ?><section class="card"><div class="empty">درخواستی مطابق فیلترهای انتخاب‌شده پیدا نشد.</div></section><?php endif; ?>
</div>

<?= render_pagination($pagination, $pageUrl) ?>
