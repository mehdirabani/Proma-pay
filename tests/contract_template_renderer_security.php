<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/models/ContractTemplateRenderer.php';

if (!class_exists('DOMDocument')) {
    fwrite(STDOUT, "BLOCKED ContractTemplateRendererSecurityTest: DOM extension is unavailable\n");
    exit(0);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$source = '<p class="contract-paragraph unapproved" onclick="alert(1)">متن <img src=x onerror=alert(2)> <a href="javascript:alert(3)">پیوند</a></p>'
    . '<script>alert(4)</script><table style="width:100%"><tr><td colspan="99">سلول</td></tr></table>';
$rendered = ContractTemplateRenderer::sanitizeHtml($source);

foreach (['<script', '<img', '<a ', 'onclick=', 'onerror=', 'javascript:', 'style='] as $unsafe) {
    $assert(stripos($rendered, $unsafe) === false, 'Unsafe markup remains after DOM sanitization: ' . $unsafe);
}
$assert(strpos($rendered, 'contract-paragraph') !== false, 'Approved class should survive sanitization.');
$assert(strpos($rendered, 'unapproved') === false, 'Unapproved class should not survive sanitization.');
$assert(strpos($rendered, 'colspan="12"') !== false, 'Table span must be constrained during sanitization.');
$assert(strpos($rendered, 'متن') !== false && strpos($rendered, 'پیوند') !== false, 'Safe text must survive sanitization.');

fwrite(STDOUT, "PASSED ContractTemplateRendererSecurityTest\n");
