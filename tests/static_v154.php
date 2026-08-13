<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$settings = require $root . '/config/settings.php';
$applicationVersion = (string) ($version['application'] ?? '');
$assert(version_compare($applicationVersion, '1.5.4', '>='), 'Core version must retain V1.5.4 capabilities.');
$assert(($version['display'] ?? '') === 'V' . $applicationVersion, 'Display version must be synchronized.');
$assert(($settings['asset_version'] ?? '') === $applicationVersion, 'Asset version must be synchronized.');

$installmentController = (string) file_get_contents($root . '/controllers/InstallmentsController.php');
$installmentModel = (string) file_get_contents($root . '/models/Installment.php');
$installmentView = (string) file_get_contents($root . '/views/installments/index.php');
$overdueCriteria = (string) file_get_contents($root . '/models/OverdueFilterCriteria.php');
$overdueService = (string) file_get_contents($root . '/models/OverdueAggregationService.php');
$overdueView = (string) file_get_contents($root . '/views/overdue/index.php');
$panels = (string) file_get_contents($root . '/assets/css/components/panels.css');
$builder = (string) file_get_contents($root . '/scripts/build_release.php');
$serviceWorker = (string) file_get_contents($root . '/service-worker.js');
$appJavascript = (string) file_get_contents($root . '/assets/js/app.js');
$adminDashboard = (string) file_get_contents($root . '/views/dashboard/admin.php');
$lawyerDashboard = (string) file_get_contents($root . '/views/dashboard/lawyer.php');
$customerDashboard = (string) file_get_contents($root . '/views/dashboard/customer.php');

$assert(strpos($installmentController, 'exclude_legal_cases') !== false, 'Installment request parsing must retain legal-case exclusion.');
$assert(strpos($installmentModel, 'exclude_legal_cases') !== false, 'Installment model must receive legal-case exclusion.');
$assert(strpos($installmentView, 'exclude_legal_cases') !== false, 'Installment UI must expose legal-case exclusion.');
$assert(strpos($overdueCriteria, 'exclude_legal_cases') !== false && strpos($overdueCriteria, 'excludeLegalCases') !== false, 'Overdue criteria must retain legal-case exclusion.');
$assert(strpos($overdueService, 'excludeLegalCases') !== false, 'Overdue service must receive legal-case exclusion.');
$assert(strpos($overdueView, 'exclude_legal_cases') !== false, 'Overdue UI must expose legal-case exclusion.');
$assert(strpos($installmentModel, "NOT EXISTS (SELECT 1 FROM legal_cases") !== false, 'Installment legal-case exclusion must be evaluated server-side.');
$assert(strpos($overdueService, "COALESCE(lc.legal_case_count, 0) = 0") !== false, 'Overdue legal-case exclusion must be evaluated server-side.');
$assert(strpos($panels, '.proma-filter-check') !== false, 'Filter checkbox alignment styles are missing.');
$assert(strpos($builder, '$templateRequiredFiles') !== false && strpos($builder, "'html/RTL/assets'") !== false, 'Required template runtime assets must be included explicitly.');
$assert(strpos($builder, "#^(?:css|fonts|images|js|json|svg)/#i") === false, 'The complete legacy template asset tree must not be included in release packages.');
$assert(strpos($serviceWorker, 'proma-pay-v' . str_replace('.', '-', $applicationVersion) . '-') !== false, 'Service-worker cache version must be synchronized.');
$assert(strpos($adminDashboard, 'style="width:') === false && strpos($adminDashboard, '<progress') !== false, 'Dashboard progress must not violate the CSP.');
$assert(strpos($lawyerDashboard, 'style="--role-accent') === false && strpos($customerDashboard, 'style="--role-accent') === false, 'Role dashboards must not rely on CSP-blocked inline styles.');
$assert(strpos($appJavascript, "if (route !== 'dashboard') return;") !== false, 'The onboarding modal must not block operational pages.');

echo "STATIC_V154_OK\n";
