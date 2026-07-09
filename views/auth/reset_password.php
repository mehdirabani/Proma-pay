<?php
$settings = Settings::allKeyed();
$systemName = $settings['system_name'] ?? app_config('app_name', 'پروما');
$logoText = $settings['logo_text'] ?? $systemName;
$logoPath = trim((string) ($settings['logo_path'] ?? ''));
$logoIconPath = trim((string) ($settings['logo_icon_path'] ?? ''));
?>
<div class="container-fluid p-0">
  <div class="row m-0">
    <div class="col-12 p-0">
      <div class="login-card login-dark proma-login-card">
        <div>
          <div>
            <a class="logo proma-login-logo" href="<?= e(url('auth/login')) ?>">
              <?php if ($logoIconPath): ?><img class="proma-uploaded-logo sm" src="<?= e(asset_url($logoIconPath)) ?>" alt="<?= e($logoText) ?>"><?php endif; ?>
              <?php if ($logoPath): ?><img class="proma-uploaded-logo" src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($logoText) ?>"><?php else: ?><span><?= e($logoText) ?></span><?php endif; ?>
            </a>
          </div>
          <div class="login-main">
            <form method="post" class="theme-form auth-form active">
              <?= csrf_field() ?>
              <h4>ثبت رمز جدید</h4>
              <p>کد ارسال‌شده<?= !empty($mobileHint) ? ' به ' . e($mobileHint) : '' ?> را وارد کنید و رمز جدید بسازید.</p>
              <?php if ($success = flash('success')): ?><div class="alert alert-light-success" role="alert"><?= e($success) ?></div><?php endif; ?>
              <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

              <div class="form-group">
                <label class="col-form-label">کد بازیابی</label>
                <input class="form-control" name="code" inputmode="numeric" pattern="[0-9۰-۹]{6}" maxlength="6" required>
              </div>
              <div class="form-group">
                <label class="col-form-label">رمز عبور جدید</label>
                <div class="form-input position-relative">
                  <input class="form-control" name="password" type="password" minlength="8" required>
                  <div class="show-hide"><span class="show"></span></div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-form-label">تکرار رمز عبور جدید</label>
                <input class="form-control" name="password_confirmation" type="password" minlength="8" required>
              </div>
              <div class="form-group mb-0">
                <button class="btn btn-primary btn-block w-100" type="submit">تغییر رمز عبور</button>
                <div class="auth-inline-actions">
                  <a href="<?= e(url('auth/forgotPassword')) ?>">دریافت کد جدید</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
