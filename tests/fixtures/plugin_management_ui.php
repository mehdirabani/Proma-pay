<?php

$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__, 2) . '/bootstrap.php';

$plugins = [
    [
        'id' => 'proma-accounting', 'name' => 'حسابداری کاربران', 'description' => 'حسابداری',
        'version' => '1.2.0', 'installed_version' => '1.2.0', 'status' => 'active',
        'path' => 'plugins/PromaAccounting', 'has_files' => true, 'has_staged_update' => false,
    ],
    [
        'id' => 'proma-inventory', 'name' => 'مدیریت انبار', 'description' => 'انبارداری',
        'version' => '1.4.0', 'installed_version' => '1.3.0', 'status' => 'update_available',
        'path' => 'plugins/PromaInventory', 'has_files' => true, 'has_staged_update' => true,
    ],
];
$pluginApiVersion = '1.0';
ob_start();
require dirname(__DIR__, 2) . '/views/plugins/index.php';
$content = ob_get_clean();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../../html/RTL/assets/css/vendors/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="../../html/RTL/assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/app.css">
  <link rel="stylesheet" href="../../assets/css/components/forms.css">
  <link rel="stylesheet" href="../../assets/css/components/layout.css">
  <style>body{padding:24px;background:#f6f7fb}.fixture{max-width:1440px;margin:auto}</style>
</head>
<body><main class="fixture proma-page-content"><?= $content ?></main>
<script src="../../html/RTL/assets/js/icons/feather-icon/feather.min.js"></script>
<script>
if(window.feather){window.feather.replace();}
document.addEventListener('click',function(event){var open=event.target.closest('[data-open-modal]');if(open){document.getElementById(open.dataset.openModal)?.classList.add('open');}if(event.target.closest('[data-close-modal]')){event.target.closest('.modal')?.classList.remove('open');}});
document.querySelectorAll('[data-check-all]').forEach(function(master){master.addEventListener('change',function(){document.querySelectorAll('[data-check-item="'+master.dataset.checkAll+'"]').forEach(function(item){item.checked=master.checked;});});});
</script>
</body></html>
