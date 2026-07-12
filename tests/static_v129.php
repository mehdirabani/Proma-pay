<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(($version['application'] ?? '') === '1.2.9', 'Application version must be V1.2.9.');

$requiredFiles = [
    'assets/css/components/layout.css',
    'docs/debug/V1_2_9_UI_LAYOUT_AUDIT.md',
    'docs/releases/V1.2.9.md',
];
foreach ($requiredFiles as $file) {
    $assert(is_file($root . '/' . $file), 'Required V1.2.9 file missing: ' . $file);
}

$layoutCss = (string) file_get_contents($root . '/assets/css/components/layout.css');
foreach ([
    '--proma-page-section-gap: 24px',
    '--proma-grid-gap-y: 20px',
    '.proma-page-content',
    '.proma-public-content',
    '.proma-role-dashboard',
    'margin-block: 0 !important',
    '@media (max-width: 767.98px)',
] as $needle) {
    $assert(strpos($layoutCss, $needle) !== false, 'Shared layout rule is missing: ' . $needle);
}

foreach (['app.php', 'auth.php', 'public.php'] as $layout) {
    $source = (string) file_get_contents($root . '/views/layouts/' . $layout);
    $assert(strpos($source, 'assets/css/components/layout.css') !== false, 'Layout stylesheet is not loaded by ' . $layout);
}

$appLayout = (string) file_get_contents($root . '/views/layouts/app.php');
$mainPosition = strpos($appLayout, '<main class="proma-page-content"');
$successPosition = strpos($appLayout, "flash('success')");
$contentPosition = strpos($appLayout, '<?= $content ?>');
$assert($mainPosition !== false && $successPosition !== false && $contentPosition !== false, 'Application content wrapper is incomplete.');
$assert($mainPosition < $successPosition && $successPosition < $contentPosition, 'Flash messages must participate in page spacing.');

foreach (['admin.php', 'operator.php', 'lawyer.php', 'customer.php'] as $dashboard) {
    $source = (string) file_get_contents($root . '/views/dashboard/' . $dashboard);
    $expected = $dashboard === 'admin.php' ? 'proma-admin-dashboard' : 'proma-role-dashboard';
    $assert(strpos($source, $expected) !== false, 'Dashboard layout hook is missing from ' . $dashboard);
}

echo "STATIC_V129_OK\n";
