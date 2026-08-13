<?php
$layoutSettings = Settings::allKeyed();
$layoutLogoIconPath = trim((string) ($layoutSettings['logo_icon_path'] ?? ''));
$layoutFaviconPath = trim((string) ($layoutSettings['favicon_path'] ?? ''));
$layoutAppIconPath = $layoutLogoIconPath ?: $layoutFaviconPath;
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'ورود') ?></title>
  <link rel="manifest" href="<?= e(url('manifest')) ?>">
  <link rel="icon" href="<?= e($layoutFaviconPath ? asset_url($layoutFaviconPath) : template_asset_url('images/favicon.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e($layoutAppIconPath ? asset_url($layoutAppIconPath) : template_asset_url('images/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/font-awesome.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/themify.css')) ?>">
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
<body>
  <main class="proma-auth-content">
    <?= $content ?>
  </main>
  <script src="<?= e(template_asset_url('js/jquery.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather-icon.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/config.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/script.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/login.js')) ?>"></script>
  <script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>
