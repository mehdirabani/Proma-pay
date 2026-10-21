<?php

require_once __DIR__ . '/../plugins/PromaRepair/src/Domain/RepairWorkflow.php';
require_once __DIR__ . '/../plugins/PromaRepair/src/Domain/RepairMoney.php';
require_once __DIR__ . '/../plugins/PromaRepair/src/Domain/FinancialSummary.php';
require_once __DIR__ . '/../plugins/PromaRepair/src/Support/SecretCipher.php';
if (!function_exists('to_english_digits')) {
    function to_english_digits($value) { return strtr((string) $value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']); }
}
require_once __DIR__ . '/../plugins/PromaRepair/src/Support/PatternLock.php';

use Proma\Plugins\Repair\Domain\FinancialSummary;
use Proma\Plugins\Repair\Domain\RepairWorkflow;
use Proma\Plugins\Repair\Support\SecretCipher;
use Proma\Plugins\Repair\Support\PatternLock;

function repairExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

repairExpect(RepairWorkflow::canTransition('diagnosing', 'waiting_customer_approval'), 'diagnosis approval transition should be allowed');
repairExpect(!RepairWorkflow::canTransition('diagnosing', 'delivered'), 'diagnosis must not transition directly to delivery');
repairExpect(!RepairWorkflow::canTransition('delivered', 'repairing'), 'delivered orders are terminal');
repairExpect(RepairWorkflow::customerLabel('quality_control') === 'در حال بررسی نهایی', 'customer status label should be understandable');
repairExpect(RepairWorkflow::canTransition('draft', 'received'), 'a draft can be registered');
repairExpect(PatternLock::normalize('1-5-9-8') === '1-5-9-8', 'pattern order is preserved');
$invalidPattern = false;
try { PatternLock::normalize('1-2-2-4'); } catch (InvalidArgumentException $ignored) { $invalidPattern = true; }
repairExpect($invalidPattern, 'pattern cannot repeat nodes');
$shortPattern = false;
try { PatternLock::normalize('1-2-3'); } catch (InvalidArgumentException $ignored) { $shortPattern = true; }
repairExpect($shortPattern, 'pattern must have at least four nodes');

$summary = FinancialSummary::calculate(
    [['amount' => 1200], ['amount' => 800]],
    [['amount' => 500], ['amount' => 100]],
    [['amount' => 300], ['amount' => -20]]
);
repairExpect($summary['total_revenue'] === 2000, 'revenue total');
repairExpect($summary['total_received'] === 600, 'received total');
repairExpect($summary['customer_balance'] === 1400, 'customer balance');
repairExpect($summary['total_cost'] === 300, 'negative ledger values must not reduce costs');
repairExpect($summary['net_profit'] === 1700, 'net profit');

$keyPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'promarepair-smoke-' . bin2hex(random_bytes(8)) . DIRECTORY_SEPARATOR . 'key';
$keyDir = dirname($keyPath);
$previousKey = getenv('PROMA_REPAIR_SECRET_KEY');
putenv('PROMA_REPAIR_SECRET_KEY');
try {
    $cipher = new SecretCipher($keyPath);
    $encrypted = $cipher->encrypt('pin-9472');
    repairExpect($cipher->decrypt($encrypted) === 'pin-9472', 'encrypted device secret round trip');
    repairExpect(strpos($encrypted, 'pin-9472') === false, 'ciphertext must not contain plaintext');

    $payload = json_decode((string) base64_decode($encrypted, true), true);
    $payload['data'] = base64_encode('tampered');
    $tampered = base64_encode(json_encode($payload));
    $rejected = false;
    try {
        $cipher->decrypt($tampered);
    } catch (RuntimeException $ignored) {
        $rejected = true;
    }
    repairExpect($rejected, 'tampered ciphertext must be rejected');
} finally {
    if ($previousKey !== false && $previousKey !== '') {
        putenv('PROMA_REPAIR_SECRET_KEY=' . $previousKey);
    } else {
        putenv('PROMA_REPAIR_SECRET_KEY');
    }
    if (is_file($keyPath)) {
        unlink($keyPath);
    }
    if (is_dir($keyDir)) {
        rmdir($keyDir);
    }
}

echo "Proma Repair domain smoke checks passed.\n";
