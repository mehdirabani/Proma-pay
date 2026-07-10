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
              <h4>ثبت‌نام مشتری</h4>
              <p>با ثبت‌نام، پنل مشتری، فروشگاه، سفارش‌ها و پرداخت اقساط برای شما فعال می‌شود.</p>
              <?php if ($success = flash('success')): ?><div class="alert alert-light-success" role="alert"><?= e($success) ?></div><?php endif; ?>
              <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

              <div class="form-group">
                <label class="col-form-label">نام و نام خانوادگی</label>
                <input class="form-control" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" autocomplete="name">
              </div>
              <div class="form-group">
                <label class="col-form-label">کد ملی</label>
                <input class="form-control" name="national_id" required inputmode="numeric" dir="ltr" value="<?= e(to_persian_digits($_POST['national_id'] ?? '')) ?>" autocomplete="username">
              </div>
              <div class="form-group">
                <label class="col-form-label">شماره تماس</label>
                <input class="form-control" name="mobile" required inputmode="tel" dir="ltr" value="<?= e(to_persian_digits($_POST['mobile'] ?? '')) ?>" autocomplete="tel">
              </div>
              <div class="form-group">
                <label class="col-form-label">ایمیل</label>
                <input class="form-control" name="email" type="email" dir="ltr" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
              </div>
              <div class="form-group">
                <label class="col-form-label">رمز عبور</label>
                <div class="form-input position-relative">
                  <input class="form-control" name="password" type="password" required autocomplete="new-password">
                  <div class="show-hide"><span class="show"></span></div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-form-label">تکرار رمز عبور</label>
                <input class="form-control" name="password_confirmation" type="password" required autocomplete="new-password">
              </div>
              <div class="form-group mb-0">
                <button class="btn btn-primary btn-block w-100" type="submit">ساخت حساب مشتری</button>
                <div class="auth-inline-actions proma-auth-secondary-action">
                  <span>حساب دارید؟</span>
                  <a href="<?= e(url('auth/login')) ?>">ورود به سامانه</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
