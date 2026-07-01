<?php
$user = Auth::user();
$settings = Settings::allKeyed();
$route = trim($_GET['route'] ?? 'dashboard', '/');
$unreadNotifications = Notification::unreadCount(Auth::id());
$unreadMessages = Chat::unreadCount(Auth::id());
$notifications = Notification::latest(Auth::id());
$systemName = $settings['system_name'] ?? app_config('app_name', 'پرما پرداخت');
$logoText = $settings['logo_text'] ?? $systemName;
$footerText = $settings['footer_text'] ?? 'پنل مدیریت مالی راست‌چین';
$sprite = template_asset_url('svg/icon-sprite.svg');
$userInitial = mb_substr($user['full_name'] ?? 'ک', 0, 1, 'UTF-8');
$nav = [];
if (Auth::role() === 'admin') {
    $nav = [
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['users', 'کاربران', 'stroke-user', 'fill-user'],
        ['customers', 'مشتریان', 'stroke-user', 'fill-user'],
        ['contracts', 'قراردادها', 'stroke-project', 'fill-project'],
        ['installments', 'اقساط', 'stroke-file', 'fill-file'],
        ['overdue', 'سررسید گذشته', 'stroke-board', 'fill-board'],
        ['payments', 'پرداخت‌ها', 'stroke-ecommerce', 'fill-ecommerce'],
        ['legal', 'حقوقی و شکایت‌ها', 'stroke-file', 'fill-file'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
        ['imports', 'ورود دیتا', 'stroke-table', 'fill-table'],
        ['calendar', 'تقویم رویدادها', 'stroke-task', 'fill-task'],
        ['ai', 'تحلیل هوشمند', 'stroke-learning', 'fill-learning'],
        ['settings', 'تنظیمات', 'stroke-others', 'fill-others'],
    ];
} elseif (Auth::role() === 'operator') {
    $nav = [
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['operator', 'پیگیری‌ها', 'stroke-task', 'fill-task'],
        ['overdue', 'سررسید گذشته', 'stroke-board', 'fill-board'],
        ['users', 'کاربران', 'stroke-user', 'fill-user'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
    ];
} elseif (Auth::role() === 'lawyer') {
    $nav = [
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['lawyer', 'پرونده‌ها', 'stroke-file', 'fill-file'],
        ['users', 'کاربران', 'stroke-user', 'fill-user'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
    ];
} else {
    $nav = [
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['portal/contracts', 'قراردادها', 'stroke-project', 'fill-project'],
        ['portal/installments', 'اقساط', 'stroke-file', 'fill-file'],
        ['portal/guaranteed', 'ضمانت‌ها', 'stroke-board', 'fill-board'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
    ];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? $systemName) ?></title>
  <link rel="manifest" href="<?= e(asset_url('manifest.json')) ?>">
  <link rel="icon" href="<?= e(template_asset_url('images/favicon.png')) ?>" type="image/x-icon">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/font-awesome.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/icofont.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/themify.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/flag-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/feather-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/slick.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/slick-theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/scrollbar.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/animate.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/bootstrap.rtl.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/style.css')) ?>">
  <link id="color" rel="stylesheet" href="<?= e(template_asset_url('css/color-1.css')) ?>" media="screen">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/responsive.css')) ?>">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
</head>
<body onload="if (window.startTime) startTime()" data-user-id="<?= (int) Auth::id() ?>">
  <div class="loader-wrapper">
    <div class="loader-index"><span></span></div>
    <svg><defs></defs><filter id="goo"><feGaussianBlur in="SourceGraphic" stdDeviation="11" result="blur"></feGaussianBlur><feColorMatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo"></feColorMatrix></filter></svg>
  </div>
  <div class="tap-top"><i data-feather="chevrons-up"></i></div>

  <div class="page-wrapper compact-wrapper" id="pageWrapper">
    <div class="page-header">
      <div class="header-wrapper row m-0">
        <form class="form-inline search-full col" method="get" action="<?= e(url($route)) ?>">
          <input type="hidden" name="route" value="<?= e($route) ?>">
          <div class="form-group w-100">
            <div class="Typeahead Typeahead--twitterUsers">
              <div class="u-posRelative">
                <input class="demo-input Typeahead-input form-control-plaintext w-100" type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="جستجو در سامانه..." title="">
                <div class="spinner-border Typeahead-spinner" role="status"><span class="sr-only">در حال بارگذاری...</span></div>
                <i class="close-search" data-feather="x"></i>
              </div>
              <div class="Typeahead-menu"></div>
            </div>
          </div>
        </form>

        <div class="header-logo-wrapper col-auto p-0">
          <div class="logo-wrapper">
            <a class="proma-template-logo" href="<?= e(url('dashboard')) ?>">
              <span class="proma-logo-mark">پ</span><span><?= e($logoText) ?></span>
            </a>
          </div>
          <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>

        <div class="left-header col-xxl-5 col-xl-6 col-lg-5 col-md-4 col-sm-3 p-0">
          <div class="notification-slider">
            <div class="d-flex h-100 align-items-center">
              <img src="<?= e(template_asset_url('images/giftools.gif')) ?>" alt="">
              <h6 class="mb-0 f-w-400"><span class="font-primary">وضعیت امروز </span><span class="f-light">مدیریت اقساط، قراردادها و پیگیری‌ها آماده است.</span></h6>
              <i class="icon-arrow-top-right f-light"></i>
            </div>
            <div class="d-flex h-100 align-items-center">
              <img src="<?= e(template_asset_url('images/giftools.gif')) ?>" alt="">
              <h6 class="mb-0 f-w-400"><span class="font-primary"><?= e(role_label(Auth::role())) ?> </span><span class="f-light">به <?= e($systemName) ?> خوش آمدید.</span></h6>
            </div>
          </div>
        </div>

        <div class="nav-right col-xxl-7 col-xl-6 col-md-7 col-8 pull-right right-header p-0 ms-auto">
          <ul class="nav-menus">
            <li><span class="header-search"><svg><use href="<?= e($sprite) ?>#search"></use></svg></span></li>
            <li>
              <div class="mode"><svg><use href="<?= e($sprite) ?>#moon"></use></svg></div>
            </li>
            <li class="onhover-dropdown">
              <div class="notification-box">
                <svg><use href="<?= e($sprite) ?>#notification"></use></svg>
                <?php if ($unreadNotifications): ?><span class="badge rounded-pill badge-secondary"><?= to_persian_digits($unreadNotifications) ?></span><?php endif; ?>
              </div>
              <div class="onhover-show-div notification-dropdown">
                <h6 class="f-18 mb-0 dropdown-title">اعلان‌ها</h6>
                <ul>
                  <?php if (!$notifications): ?>
                    <li><p class="f-light mb-0">اعلان تازه‌ای ندارید.</p></li>
                  <?php else: foreach ($notifications as $item): ?>
                    <li class="b-l-primary border-4">
                      <a href="<?= e($item['url'] ?: url('dashboard')) ?>">
                        <p><?= e($item['title']) ?><span class="font-primary"><?= e($item['body']) ?></span></p>
                      </a>
                    </li>
                  <?php endforeach; endif; ?>
                  <li>
                    <form method="post" action="<?= e(url('notifications/read')) ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn-primary btn-sm w-100" type="submit">خواندن همه</button>
                    </form>
                  </li>
                </ul>
              </div>
            </li>
            <li>
              <a class="notification-box proma-header-link" href="<?= e(url('chat')) ?>">
                <svg><use href="<?= e($sprite) ?>#stroke-chat"></use></svg>
                <?php if ($unreadMessages): ?><span class="badge rounded-pill badge-success"><?= to_persian_digits($unreadMessages) ?></span><?php endif; ?>
              </a>
            </li>
            <li class="profile-nav onhover-dropdown pe-0 py-0">
              <div class="media profile-media">
                <span class="proma-avatar"><?= e($userInitial) ?></span>
                <div class="media-body">
                  <span><?= e($user['full_name'] ?? '') ?></span>
                  <p class="mb-0"><?= e(role_label($user['role'] ?? '')) ?> <i class="middle fa fa-angle-down"></i></p>
                </div>
              </div>
              <ul class="profile-dropdown onhover-show-div">
                <li><a href="<?= e(url('profile')) ?>"><i data-feather="user"></i><span>پروفایل من</span></a></li>
                <li><a href="<?= e(url('chat')) ?>"><i data-feather="message-square"></i><span>پیام‌ها</span></a></li>
                <li>
                  <form method="post" action="<?= e(url('auth/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="dropdown-form-button" type="submit"><i data-feather="log-out"></i><span>خروج</span></button>
                  </form>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="page-body-wrapper">
      <div class="sidebar-wrapper" sidebar-layout="stroke-svg">
        <div>
          <div class="logo-wrapper">
            <a class="proma-template-logo" href="<?= e(url('dashboard')) ?>">
              <span class="proma-logo-mark">پ</span><span><?= e($logoText) ?></span>
            </a>
            <div class="back-btn"><i class="fa fa-angle-left"></i></div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="grid"></i></div>
          </div>
          <div class="logo-icon-wrapper">
            <a href="<?= e(url('dashboard')) ?>"><span class="proma-logo-mark sm">پ</span></a>
          </div>
          <nav class="sidebar-main">
            <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
            <div id="sidebar-menu">
              <ul class="sidebar-links" id="simple-bar">
                <li class="back-btn">
                  <a href="<?= e(url('dashboard')) ?>"><span class="proma-logo-mark sm">پ</span></a>
                  <div class="mobile-back text-end"><span>برگشت</span><i class="fa fa-angle-right ps-2" aria-hidden="true"></i></div>
                </li>
                <li class="pin-title sidebar-main-title"><div><h6>پین شده</h6></div></li>
                <li class="sidebar-main-title"><div><h6>منوی سامانه</h6></div></li>
                <?php foreach ($nav as $item): ?>
                  <?php $active = strpos($route, $item[0]) === 0 || ($route === 'dashboard' && $item[0] === 'dashboard'); ?>
                  <li class="sidebar-list">
                    <i class="fa fa-thumb-tack"></i>
                    <?php if ($item[0] === 'chat' && $unreadMessages): ?><label class="badge badge-light-primary"><?= to_persian_digits($unreadMessages) ?></label><?php endif; ?>
                    <a class="sidebar-link sidebar-title link-nav <?= $active ? 'active' : '' ?>" href="<?= e(url($item[0])) ?>">
                      <svg class="stroke-icon"><use href="<?= e($sprite) ?>#<?= e($item[2]) ?>"></use></svg>
                      <svg class="fill-icon"><use href="<?= e($sprite) ?>#<?= e($item[3]) ?>"></use></svg>
                      <span><?= e($item[1]) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
          </nav>
        </div>
      </div>

      <div class="page-body">
        <div class="container-fluid">
          <div class="page-title">
            <div class="row">
              <div class="col-6">
                <h4><?= e($title ?? '') ?></h4>
              </div>
              <div class="col-6">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="<?= e(url('dashboard')) ?>"><svg class="stroke-icon"><use href="<?= e($sprite) ?>#stroke-home"></use></svg></a></li>
                  <li class="breadcrumb-item"><?= e($systemName) ?></li>
                  <li class="breadcrumb-item active"><?= e(role_label(Auth::role())) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>
        <div class="container-fluid">
          <?php if ($success = flash('success')): ?><div class="alert alert-light-success" role="alert"><?= e($success) ?></div><?php endif; ?>
          <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
          <?= $content ?>
        </div>
      </div>

      <div class="proma-tour" data-tour hidden>
        <div class="proma-tour-backdrop"></div>
        <div class="proma-tour-card">
          <span class="badge badge-light-primary" data-tour-step>۱ از ۴</span>
          <h5 data-tour-title>خوش آمدید</h5>
          <p data-tour-body>در این تور کوتاه با بخش‌های اصلی پنل آشنا می‌شوید.</p>
          <div class="actions">
            <button class="btn secondary" type="button" data-tour-skip>رد کردن</button>
            <button class="btn" type="button" data-tour-next>بعدی</button>
          </div>
        </div>
      </div>

      <div class="modal proma-jalali-modal" id="proma-jalali-modal" data-jalali-modal>
        <div class="modal-content">
          <div class="modal-header">
            <h3 data-jalali-title>انتخاب تاریخ شمسی</h3>
            <button class="icon-btn" type="button" data-close-modal>×</button>
          </div>
          <div class="modal-body">
            <div class="proma-jalali-toolbar">
              <button class="icon-btn" type="button" data-jalali-prev title="ماه قبل">‹</button>
              <label>سال<input data-jalali-year inputmode="numeric"></label>
              <label>ماه<select data-jalali-month></select></label>
              <button class="icon-btn" type="button" data-jalali-next title="ماه بعد">›</button>
            </div>
            <div class="proma-jalali-grid" data-jalali-grid></div>
          </div>
          <div class="modal-footer">
            <button class="btn secondary" type="button" data-jalali-today>امروز</button>
            <button class="btn secondary" type="button" data-close-modal>بستن</button>
          </div>
        </div>
      </div>

      <footer class="footer">
        <div class="container-fluid">
          <div class="row">
            <div class="col-md-12 footer-copyright text-center">
              <p class="mb-0"><?= e($systemName) ?> - <?= e($footerText) ?></p>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script src="<?= e(template_asset_url('js/jquery.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather-icon.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/scrollbar/simplebar.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/scrollbar/custom.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/config.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/sidebar-menu.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/sidebar-pin.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/clock.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/slick/slick.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/slick/slick.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/header-slick.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/height-equal.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/script.js')) ?>"></script>
  <script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>
