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
            <a class="logo proma-login-logo" href="<?= e(url('auth/upgradePassword')) ?>">
              <?php if ($logoIconPath): ?><img class="proma-uploaded-logo sm" src="<?= e(asset_url($logoIconPath)) ?>" alt="<?= e($logoText) ?>"><?php endif; ?>
              <?php if ($logoPath): ?><img class="proma-uploaded-logo" src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($logoText) ?>"><?php else: ?><span><?= e($logoText) ?></span><?php endif; ?>
            </a>
          </div>
          <div class="login-main">
            <form method="post" class="theme-form auth-form active">
              <?= csrf_field() ?>
              <h4>ثبت رمز عبور جدید</h4>
              <p>برای ادامهٔ استفاده از حساب، یک رمز شخصی حداقل ۸ نویسه‌ای انتخاب کنید.</p>
              <?php if ($success = flash('success')): ?><div class="alert alert-light-success" role="status" aria-live="polite" aria-atomic="true"><?= e($success) ?></div><?php endif; ?>
              <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

              <div class="form-group">
                <label class="col-form-label" for="customer-new-password">رمز عبور جدید</label>
                <input id="customer-new-password" class="form-control" name="password" type="password" minlength="8" required autocomplete="new-password">
              </div>
              <div class="form-group">
                <label class="col-form-label" for="customer-new-password-confirmation">تکرار رمز عبور جدید</label>
                <input id="customer-new-password-confirmation" class="form-control" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
              </div>
              <div class="form-group mb-0">
                <button class="btn btn-primary btn-block w-100" type="submit">ذخیره رمز و ادامه</button>
              </div>
            </form>
            <form method="post" action="<?= e(url('auth/logout')) ?>" class="auth-inline-actions">
              <?= csrf_field() ?>
              <button class="btn secondary" type="submit">خروج از حساب</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
