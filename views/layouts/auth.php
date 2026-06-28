<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'ورود') ?></title>
  <link rel="manifest" href="<?= e(asset_url('manifest.json')) ?>">
  <link rel="icon" href="<?= e(template_asset_url('images/favicon.png')) ?>" type="image/x-icon">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/font-awesome.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/icofont.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/themify.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/flag-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/feather-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/bootstrap.rtl.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/style.css')) ?>">
  <link id="color" rel="stylesheet" href="<?= e(template_asset_url('css/color-1.css')) ?>" media="screen">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/responsive.css')) ?>">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body>
  <?= $content ?>
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
