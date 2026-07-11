<div class="row">
  <div class="col-xl-4">
    <section class="card proma-profile-card">
      <div class="card-body">
        <div class="proma-avatar-choice <?= e(normalize_avatar_key($user['avatar_key'] ?? 'avatar-1')) ?>" aria-label="<?= e($user['full_name']) ?>"></div>
        <h4><?= e($user['full_name']) ?> <?php if (!empty($identityVerified)): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓ آبی</span><?php endif; ?></h4>
        <p><?= e(role_label($user['role'])) ?></p>
        <?php if ($latestRequest): ?>
          <span class="badge badge-light-<?= e(badge_class($latestRequest['status'])) ?>"><?= e(status_label($latestRequest['status'])) ?></span>
        <?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-xl-8">
    <section class="card">
      <div class="card-header card-no-border"><h5>ویرایش پروفایل</h5></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('profile/update')) ?>" class="form-grid two">
          <?= csrf_field() ?>
          <label>نام کامل<input name="full_name" value="<?= e($user['full_name']) ?>" required></label>
          <label>موبایل<input name="mobile" value="<?= e($user['mobile']) ?>" required inputmode="tel"></label>
          <label>تلفن دوم<input name="secondary_phone" value="<?= e($user['secondary_phone']) ?>" inputmode="tel"></label>
          <label>ایمیل<input name="email" value="<?= e($user['email']) ?>" type="email" dir="ltr"></label>
          <label class="full">آدرس<textarea name="address"><?= e($user['address'] ?? '') ?></textarea></label>
          <label>رمز عبور تازه<input name="password" type="password" placeholder="در صورت تغییر وارد کنید"></label>
          <div class="full">
            <span class="field-title">آواتار پیش‌فرض</span>
            <div class="proma-avatar-options">
              <?php foreach ($avatars as $avatar): ?>
                <label>
                  <input type="radio" name="avatar_key" value="<?= e($avatar) ?>"<?= checked($user['avatar_key'] ?? 'avatar-1', $avatar) ?>>
                  <span class="proma-avatar-choice <?= e($avatar) ?>" aria-label="<?= e($avatar) ?>"></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="full notice info">ویرایش کاربران غیرمدیر پس از تایید مدیریت روی حساب اعمال می‌شود.</div>
          <div class="full"><button class="btn" type="submit">ثبت درخواست ویرایش</button></div>
        </form>
      </div>
    </section>
  </div>
</div>

<section class="card">
  <div class="card-header card-no-border"><h5>مدارک هویتی</h5></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('profile/uploadIdentity')) ?>" enctype="multipart/form-data" class="form-grid three">
      <?= csrf_field() ?>
      <label>تصویر کارت ملی<input type="file" name="national_card" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
      <label>تصویر شناسنامه<input type="file" name="birth_certificate" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
      <label>توضیحات<input name="identity_note" placeholder="در صورت نیاز"></label>
      <div class="full notice info">حداکثر حجم هر تصویر ۱۰ مگابایت است. مدارک جدید تا زمان تأیید مدیریت جایگزین مدارک قبلی نمی‌شوند.</div>
      <div class="full"><button class="btn" type="submit">ارسال برای بررسی</button></div>
    </form>

    <div class="table-wrap" style="margin-top:16px">
      <table>
        <thead><tr><th>نوع مدرک</th><th>وضعیت</th><th>بارگذاری</th><th>بررسی</th><th>فایل</th></tr></thead>
        <tbody>
        <?php foreach (($identityDocuments ?? []) as $document): ?>
          <tr>
            <td><?= e(IdentityDocument::typeLabel($document['document_type'])) ?></td>
            <td><span class="badge <?= e(badge_class($document['status'])) ?>"><?= e(status_label($document['status'])) ?></span></td>
            <td><?= e(jdatetime($document['uploaded_at'])) ?></td>
            <td><?= !empty($document['reviewed_at']) ? e(jdatetime($document['reviewed_at'])) : '-' ?></td>
            <td>
              <?php if (!empty($document['file_path'])): ?>
                <a class="btn small secondary" href="<?= e(url('profile/identityFile/' . $document['id'])) ?>" target="_blank">مشاهده</a>
              <?php else: ?>
                <span class="badge muted">فایل بررسی و حذف شد</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($identityDocuments)): ?><tr><td colspan="5" class="empty">مدرکی بارگذاری نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
