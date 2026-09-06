<?php
$layoutSettings = Settings::allKeyed();
$systemName = $layoutSettings['system_name'] ?? app_config('app_name', 'پروما');
$logoText = $layoutSettings['logo_text'] ?? $systemName;
$logoPath = trim((string) ($layoutSettings['logo_path'] ?? ''));
$logoIconPath = trim((string) ($layoutSettings['logo_icon_path'] ?? ''));
$faviconPath = trim((string) ($layoutSettings['favicon_path'] ?? ''));
$appIconPath = $logoIconPath ?: $faviconPath;
$footerText = $layoutSettings['footer_text'] ?? 'پروما پی سامانه جامع پرداخت';
$publicHomeRoute = 'auth/login';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? $systemName) ?></title>
  <link rel="manifest" href="<?= e(url('manifest')) ?>">
  <link rel="icon" href="<?= e($faviconPath ? asset_url($faviconPath) : template_asset_url('images/favicon.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e($appIconPath ? asset_url($appIconPath) : template_asset_url('images/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/font-awesome.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/feather-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/bootstrap.rtl.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/style.css')) ?>">
  <link id="color" rel="stylesheet" href="<?= e(template_asset_url('css/color-1.css')) ?>" media="screen">
   <link rel="stylesheet" href="<?= e(template_asset_url('css/responsive.css')) ?>">
   <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
   <link rel="stylesheet" href="<?= e(asset_url('assets/css/design-system/tokens.css')) ?>">
   <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/forms.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/responsive.css')) ?>">
</head>
<body class="proma-public-body">
  <header class="proma-public-header">
    <a class="proma-template-logo" href="<?= e(url($publicHomeRoute)) ?>">
      <?php if ($logoIconPath): ?><img class="proma-uploaded-logo sm" src="<?= e(asset_url($logoIconPath)) ?>" alt="<?= e($logoText) ?>"><?php endif; ?>
      <?php if ($logoPath): ?><img class="proma-uploaded-logo" src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($logoText) ?>"><?php else: ?><span><?= e($logoText) ?></span><?php endif; ?>
    </a>
    <nav>
      <a href="<?= e(url('auth/login')) ?>">ورود</a>
      <a class="proma-public-register" href="<?= e(url('auth/register')) ?>">ثبت‌نام مشتری</a>
    </nav>
  </header>

  <main class="proma-public-main">
  <div class="proma-public-content">
    <?php if ($success = flash('success')): ?><div class="alert alert-light-success" role="alert"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= $content ?>
  </div>
  </main>

  <footer class="proma-public-footer">
    <span><?= e($footerText) ?></span>
    <a href="<?= e(url('auth/register')) ?>">ساخت حساب مشتری</a>
  </footer>

  <script src="<?= e(template_asset_url('js/jquery.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather-icon.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/config.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/script.js')) ?>"></script>
  <script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>
