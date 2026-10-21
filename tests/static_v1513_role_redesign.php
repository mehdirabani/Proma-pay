<?php

$root = dirname(__DIR__);

$layout = file_get_contents($root . '/views/layouts/app.php');
$cssPath = $root . '/assets/css/components/role-redesign.css';
$version = require $root . '/config/version.php';

if (!is_file($cssPath)) {
    fwrite(STDERR, "role-redesign.css is missing\n");
    exit(1);
}

$css = file_get_contents($cssPath);

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assertContains($layout, "assets/css/components/role-redesign.css", 'Role redesign stylesheet is not loaded by the main layout.');
$assertContains($css, '.proma-role-dashboard', 'Role dashboard redesign selector is missing.');
$assertContains($css, '.proma-admin-dashboard', 'Admin dashboard redesign selector is missing.');
$assertContains($css, '.proma-filter-toolbar', 'Filter toolbar alignment selector is missing.');
$assertContains($css, '.proma-contract-card', 'Contract card redesign selector is missing.');
$assertContains($css, '.proma-customer-card', 'Customer card redesign selector is missing.');
$assertContains($css, '.proma-purchase-contract-card', 'Customer purchase history card selector is missing.');
$assertContains($css, '.proma-overdue-mobile-card', 'Operator overdue card selector is missing.');
$assertContains($css, '.modal.open .modal-content', 'Modal redesign selector is missing.');
$assertContains($css, 'sidebar-wrapper', 'Sidebar redesign selector is missing.');
$assertContains($css, '@media (max-width: 767.98px)', 'Mobile responsive redesign rules are missing.');

if (version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.13', '<')) {
    fwrite(STDERR, "Expected application version 1.5.13 or newer\n");
    exit(1);
}

echo "v1.5.13 role redesign static checks passed\n";
