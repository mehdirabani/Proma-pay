<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$customersView = file_get_contents($root . '/views/customers/index.php');
$appCss = file_get_contents($root . '/assets/css/app.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(is_string($customersView) && is_string($appCss), 'Customer progress assets could not be read.');
$assert(str_contains($customersView, 'class="proma-progress-avatar" data-progress='), 'Customer progress must use the CSP-compatible data-progress hook.');
$assert(!str_contains($customersView, 'proma-progress-avatar" style='), 'Customer progress must not emit an inline style attribute.');
$assert(str_contains($customersView, 'proma-medal-badge'), 'Customer medal badges must use their named CSS class.');
$assert(str_contains($appCss, '.proma-progress-avatar') && str_contains($appCss, '.proma-medal-badge'), 'Customer progress and medal styles must remain in the stylesheet.');

echo "CUSTOMER_CSP_INLINE_STYLE_REGRESSION_OK\n";
