<div class="row">
  <div class="col-xl-4">
    <section class="card proma-profile-card">
      <div class="card-body">
        <div class="proma-avatar-choice <?= e($user['avatar_key'] ?? 'avatar-1') ?>"><?= e(mb_substr($user['full_name'], 0, 1, 'UTF-8')) ?></div>
        <h4><?= e($user['full_name']) ?></h4>
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
                  <span class="proma-avatar-choice <?= e($avatar) ?>"><?= to_persian_digits(substr($avatar, -1)) ?></span>
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
