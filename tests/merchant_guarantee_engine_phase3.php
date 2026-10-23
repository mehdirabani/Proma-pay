<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/MerchantGuaranteeCalculator.php';
require_once __DIR__ . '/../helpers/MerchantGuaranteePolicy.php';

function merchantGuaranteeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function merchantGuaranteeRejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        return;
    }
    throw new RuntimeException($message);
}

merchantGuaranteeAssert(MerchantGuaranteeCalculator::parseMultiplierUnits('2.00') === 20000, '2.00 multiplier parsed incorrectly.');
merchantGuaranteeRejects(static function (): void {
    MerchantGuaranteeCalculator::parseMultiplierUnits('۱٫۵۰');
}, 'Non-canonical decimal separator was silently accepted.');
merchantGuaranteeAssert(MerchantGuaranteeCalculator::parseMultiplierUnits('۱.۵۰') === 15000, 'Persian digits were not normalized.');
merchantGuaranteeRejects(static function (): void {
    MerchantGuaranteeCalculator::parseMultiplierUnits('0');
}, 'Zero multiplier was accepted.');
merchantGuaranteeRejects(static function (): void {
    MerchantGuaranteeCalculator::parseMultiplierUnits('1.00001');
}, 'Multiplier precision above four decimal places was accepted.');

$schedule = [
    ['installment_number' => 2, 'due_date' => '2027-02-01', 'amount' => '3250000.00'],
    ['installment_number' => 1, 'due_date' => '2027-01-01', 'amount' => '3250000.00'],
];
$double = MerchantGuaranteeCalculator::calculate($schedule, 20000);
merchantGuaranteeAssert($double['scheduled_installments_toman'] === 6500000, 'Scheduled installment total is wrong.');
merchantGuaranteeAssert($double['cheque_amount_rial'] === 130000000, '2.00 cheque amount is wrong in Rials.');
merchantGuaranteeAssert($double['cheque_due_date'] === '2027-01-01', 'Cheque date did not use the first scheduled installment.');
merchantGuaranteeAssert($double['first_installment_due_date'] === $double['cheque_due_date'], 'Cheque date and first installment differ.');

$oneAndHalf = MerchantGuaranteeCalculator::calculate($schedule, 15000);
merchantGuaranteeAssert($oneAndHalf['cheque_amount_rial'] === 97500000, '1.50 cheque amount is wrong in Rials.');
$rounded = MerchantGuaranteeCalculator::calculate([
    ['installment_number' => 1, 'due_date' => '2027-01-01', 'amount' => '1'],
], 10500);
merchantGuaranteeAssert($rounded['cheque_amount_rial'] === 11, 'Half-up rounding to the nearest Rial is incorrect.');

merchantGuaranteeRejects(static function () use ($schedule): void {
    MerchantGuaranteeCalculator::calculate([
        ['installment_number' => 1, 'due_date' => '2027-01-01', 'amount' => '1.25'],
    ], 20000);
}, 'Fractional Toman installment was accepted.');
merchantGuaranteeRejects(static function (): void {
    MerchantGuaranteeCalculator::calculate([
        ['installment_number' => 1, 'due_date' => '2027-02-30', 'amount' => '100'],
    ], 20000);
}, 'Invalid installment date was accepted.');
merchantGuaranteeRejects(static function (): void {
    MerchantGuaranteeCalculator::calculate([
        ['installment_number' => 1, 'due_date' => '2027-01-01', 'amount' => '100'],
        ['installment_number' => 1, 'due_date' => '2027-02-01', 'amount' => '100'],
    ], 20000);
}, 'Duplicate installment number was accepted.');

$centralPolicy = [
    'id' => 7,
    'status' => 'published',
    'enabled' => 1,
    'minimum_multiplier_units' => 15000,
    'maximum_multiplier_units' => 25000,
    'default_multiplier_units' => 20000,
    'beneficiary_legal_name' => 'شرکت نمونه',
    'beneficiary_identifier' => '12345678901',
    'legal_approval_reference' => 'LEGAL-APPROVAL-2026-001',
];
$connectionPolicy = [
    'guarantee_type' => 'sayadi_cheque',
    'required_cheque_count' => 1,
    'guarantee_base' => 'scheduled_installments',
    'cheque_multiplier' => '2.50',
];
$resolved = MerchantGuaranteePolicy::resolve($connectionPolicy, $centralPolicy);
merchantGuaranteeAssert($resolved['multiplier_units'] === 25000, 'Connection multiplier resolution is wrong.');
merchantGuaranteeAssert($resolved['beneficiary_identifier'] === '12345678901', 'Central beneficiary snapshot is wrong.');
$defaulted = MerchantGuaranteePolicy::resolve(array_diff_key($connectionPolicy, ['cheque_multiplier' => true]), $centralPolicy);
merchantGuaranteeAssert($defaulted['multiplier_units'] === 20000, 'Published central default was not applied.');

$outOfRange = $connectionPolicy;
$outOfRange['cheque_multiplier'] = '2.5001';
merchantGuaranteeRejects(static function () use ($outOfRange, $centralPolicy): void {
    MerchantGuaranteePolicy::resolve($outOfRange, $centralPolicy);
}, 'Connection policy exceeded the central maximum.');
$wrongGuarantee = $connectionPolicy;
$wrongGuarantee['required_cheque_count'] = 2;
merchantGuaranteeRejects(static function () use ($wrongGuarantee, $centralPolicy): void {
    MerchantGuaranteePolicy::resolve($wrongGuarantee, $centralPolicy);
}, 'WooCommerce policy allowed more than one cheque.');
$fractionalCount = $connectionPolicy;
$fractionalCount['required_cheque_count'] = '1.5';
merchantGuaranteeRejects(static function () use ($fractionalCount, $centralPolicy): void {
    MerchantGuaranteePolicy::resolve($fractionalCount, $centralPolicy);
}, 'Non-integer required cheque count was accepted.');
$unpublished = $centralPolicy;
$unpublished['status'] = 'draft';
merchantGuaranteeRejects(static function () use ($connectionPolicy, $unpublished): void {
    MerchantGuaranteePolicy::resolve($connectionPolicy, $unpublished);
}, 'Draft central policy was used for a guarantee.');
$disabledCentral = $centralPolicy;
$disabledCentral['enabled'] = '01';
merchantGuaranteeRejects(static function () use ($connectionPolicy, $disabledCentral): void {
    MerchantGuaranteePolicy::resolve($connectionPolicy, $disabledCentral);
}, 'Malformed central enable flag was accepted.');
$missingApproval = $centralPolicy;
$missingApproval['legal_approval_reference'] = '';
merchantGuaranteeRejects(static function () use ($connectionPolicy, $missingApproval): void {
    MerchantGuaranteePolicy::resolve($connectionPolicy, $missingApproval);
}, 'Guarantee policy without a legal approval reference was accepted.');

echo "MERCHANT_GUARANTEE_ENGINE_PHASE3_OK\n";
