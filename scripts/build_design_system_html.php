<?php

/**
 * Export the current interactive design system as a static HTML page.
 * Run from the repository root with: php scripts/build_design_system_html.php
 */
if (PHP_SAPI !== 'cli' && !defined('PROMA_DESIGN_SYSTEM_HTML_EXPORT')) {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once __DIR__ . '/../helpers/functions.php';

class Auth
{
    public static function requireRole($roles): void
    {
        // The static export contains documentation and mock data only.
    }
}

class Controller
{
    protected function render($view, $data, $layout = null): void
    {
        extract($data, EXTR_SKIP);
        include __DIR__ . '/../views/' . $view . '.php';
    }
}

require_once __DIR__ . '/../controllers/DesignSystemController.php';

ob_start();
(new DesignSystemController())->index();
$html = (string) ob_get_clean();

if (strpos($html, 'PROMA PAY DESIGN SYSTEM') === false) {
    fwrite(STDERR, "Could not render the design system HTML.\n");
    exit(1);
}

// Convert app-root asset paths to paths relative to this file so it also opens
// directly from the repository without a PHP route or server-side request.
$html = preg_replace('~\b(href|src)="[^"]*/(html/RTL/assets/|assets/)([^"]+)"~', '$1="$2$3"', $html);
$html = preg_replace('~\bhref="[^"]*/index\.php\?~', 'href="index.php?', $html);

$target = __DIR__ . '/../design-system.html';
if (file_put_contents($target, $html) === false) {
    fwrite(STDERR, "Could not write design-system.html.\n");
    exit(1);
}

fwrite(STDOUT, 'Wrote design-system.html (' . number_format(strlen($html)) . " bytes).\n");
