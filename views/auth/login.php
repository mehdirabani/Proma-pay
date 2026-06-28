<?php
$settings = Settings::allKeyed();
$systemName = $settings['system_name'] ?? app_config('app_name', 'پرما پرداخت');
$logoText = $settings['logo_text'] ?? $systemName;
?>
<div class="container-fluid p-0">
  <div class="row m-0">
    <div class="col-12 p-0">
      <div class="login-card login-dark proma-login-card">
        <div>
          <div>
            <a class="logo proma-login-logo" href="<?= e(url('auth/login')) ?>">
              <span class="proma-logo-mark">پ</span>
              <span><?= e($logoText) ?></span>
            </a>
          </div>
          <div class="login-main">
            <form method="post" class="theme-form auth-form active">
              <?= csrf_field() ?>
              <h4>ورود به سامانه</h4>
              <p>شناسه خود را وارد کنید؛ سامانه نوع حساب شما را به صورت خودکار تشخیص می‌دهد.</p>
              <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

              <div class="form-group">
                <label class="col-form-label">شناسه ورود</label>
                <input class="form-control" name="identifier" required placeholder="نام کاربری، کد ملی، موبایل یا ایمیل">
              </div>
              <div class="form-group">
                <label class="col-form-label">رمز عبور</label>
                <div class="form-input position-relative">
                  <input class="form-control" name="password" type="password" required placeholder="کارکنان: رمز عبور، مشتریان: چهار رقم آخر موبایل">
                  <div class="show-hide"><span class="show"></span></div>
                </div>
              </div>
              <div class="form-group mb-0">
                <div class="checkbox p-0">
                  <input id="secure-login" type="checkbox" disabled>
                  <label class="text-muted" for="secure-login">ورود امن برای کارکنان و مشتریان</label>
                </div>
                <button class="btn btn-primary btn-block w-100" type="submit">ورود</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
