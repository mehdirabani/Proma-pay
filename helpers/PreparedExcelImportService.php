<?php

class PreparedExcelImportService
{
    public static function import(array $data, $userId = null)
    {
        Contract::ensureSchema();
        Installment::ensureSchema();
        User::ensureProfileColumns();

        $summary = [
            'customers_created' => 0,
            'customers_existing' => 0,
            'contracts_created' => 0,
            'contracts_existing' => 0,
            'installments_created' => 0,
            'installments_existing' => 0,
            'skipped' => [],
        ];

        $customers = self::normalizeRows($data['customers'] ?? []);
        $contracts = self::normalizeRows($data['contracts'] ?? []);
        $installments = self::normalizeRows($data['installments'] ?? []);
        $installmentsByContract = self::groupInstallments($installments);
        $customerMap = [];
        $contractMap = [];

        Model::begin();
        try {
            foreach ($customers as $row) {
                $sourceId = self::text($row['customer_id'] ?? '');
                $fullName = self::text($row['full_name'] ?? '');
                $nationalId = self::digits($row['national_id'] ?? '');
                $mobile = self::normalizePhone($row['phone_1'] ?? $row['mobile'] ?? '');
                $secondary = self::normalizePhone($row['phone_2'] ?? $row['secondary_phone'] ?? '');
                if ($fullName === '' || ($nationalId === '' && $mobile === '')) {
                    $summary['skipped'][] = 'مشتری ناقص: ' . ($sourceId ?: $fullName ?: 'بدون شناسه');
                    continue;
                }
                $existing = self::findCustomer($nationalId, $mobile);
                if ($existing) {
                    $customerId = (int) $existing['id'];
                    self::fillCustomerBlanks($customerId, $existing, [
                        'full_name' => $fullName,
                        'national_id' => $nationalId,
                        'mobile' => $mobile,
                        'secondary_phone' => $secondary,
                    ]);
                    $summary['customers_existing']++;
                } else {
                    $customerId = User::create([
                        'role' => 'customer',
                        'username' => null,
                        'full_name' => $fullName,
                        'national_id' => $nationalId,
                        'mobile' => $mobile,
                        'secondary_phone' => $secondary,
                        'email' => '',
                        'password' => '',
                        'status' => 'active',
                        'address' => '',
                    ]);
                    $summary['customers_created']++;
                }
                if ($sourceId !== '') {
                    $customerMap['source:' . $sourceId] = $customerId;
                }
                if ($nationalId !== '') {
                    $customerMap['national:' . $nationalId] = $customerId;
                }
                if ($mobile !== '') {
                    $customerMap['mobile:' . $mobile] = $customerId;
                }
            }

            foreach ($contracts as $row) {
                $contractNumber = self::text($row['contract_number'] ?? '');
                if ($contractNumber === '') {
                    $summary['skipped'][] = 'قرارداد بدون شماره از ورود دیتا رد شد.';
                    continue;
                }
                $existing = Model::fetch('SELECT id, customer_id FROM contracts WHERE contract_number = ?', [$contractNumber]);
                if ($existing) {
                    $contractMap[$contractNumber] = (int) $existing['id'];
                    $summary['contracts_existing']++;
                    continue;
                }

                $customerId = self::resolveCustomerId($row, $customerMap);
                if (!$customerId) {
                    $summary['skipped'][] = 'مشتری قرارداد ' . $contractNumber . ' پیدا نشد.';
                    continue;
                }

                $contractInstallments = $installmentsByContract[$contractNumber] ?? [];
                $firstDueDate = self::parseDate($row['first_due_date'] ?? '') ?: self::firstDueDate($contractInstallments);
                if (!$firstDueDate) {
                    $summary['skipped'][] = 'تاریخ اولین سررسید قرارداد ' . $contractNumber . ' معتبر نیست.';
                    continue;
                }
                $total = normalize_money($row['total_installments_amount_toman'] ?? 0);
                if ($total <= 0) {
                    $total = self::sumInstallments($contractInstallments);
                }
                $months = (int) to_english_digits($row['installments_count'] ?? count($contractInstallments));
                $months = max(1, $months);
                if ($total <= 0) {
                    $summary['skipped'][] = 'مبلغ قرارداد ' . $contractNumber . ' معتبر نیست.';
                    continue;
                }

                $contractId = self::insertContract([
                    'customer_id' => $customerId,
                    'contract_number' => $contractNumber,
                    'principal_amount' => $total,
                    'months' => $months,
                    'start_date' => $firstDueDate,
                    'first_due_date' => $firstDueDate,
                    'status' => self::normalizeContractStatus($row['status'] ?? 'active'),
                    'notes' => 'وارد شده از فایل اکسل 1.xlsx' . (!empty($row['contract_id']) ? ' - شناسه: ' . self::text($row['contract_id']) : ''),
                ], $userId);
                $contractMap[$contractNumber] = $contractId;
                $summary['contracts_created']++;
            }

            foreach ($installments as $row) {
                $contractNumber = self::text($row['contract_number'] ?? '');
                $contractId = $contractMap[$contractNumber] ?? null;
                if (!$contractId && $contractNumber !== '') {
                    $contract = Model::fetch('SELECT id FROM contracts WHERE contract_number = ?', [$contractNumber]);
                    $contractId = $contract ? (int) $contract['id'] : null;
                }
                $number = (int) to_english_digits($row['installment_number'] ?? 0);
                $dueDate = self::parseDate($row['due_date'] ?? '');
                $amount = normalize_money($row['amount_toman'] ?? $row['amount'] ?? 0);
                if (!$contractId || $number <= 0 || !$dueDate || $amount <= 0) {
                    $summary['skipped'][] = 'قسط ناقص قرارداد ' . ($contractNumber ?: '-') . ' رد شد.';
                    continue;
                }
                $exists = Model::fetch('SELECT id FROM installments WHERE contract_id = ? AND installment_number = ?', [$contractId, $number]);
                if ($exists) {
                    $summary['installments_existing']++;
                    continue;
                }
                $status = self::normalizeInstallmentStatus($row['status'] ?? '', $dueDate);
                $paid = $status === 'paid' ? $amount : 0;
                Model::execute(
                    'INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, notes, is_custom, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())',
                    [
                        $contractId,
                        $number,
                        $dueDate,
                        $amount,
                        $paid,
                        max(0, $amount - $paid),
                        $status,
                        !empty($row['installment_id']) ? 'شناسه ورود دیتا: ' . self::text($row['installment_id']) : null,
                    ]
                );
                $summary['installments_created']++;
            }

            self::syncNextSerial();
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }

        return $summary;
    }

    protected static function normalizeRows(array $rows)
    {
        return array_values(array_filter($rows, function ($row) {
            return is_array($row);
        }));
    }

    protected static function groupInstallments(array $rows)
    {
        $grouped = [];
        foreach ($rows as $row) {
            $number = self::text($row['contract_number'] ?? '');
            if ($number !== '') {
                $grouped[$number][] = $row;
            }
        }
        return $grouped;
    }

    protected static function findCustomer($nationalId, $mobile)
    {
        if ($nationalId !== '') {
            $row = Model::fetch("SELECT * FROM users WHERE role = 'customer' AND national_id = ? LIMIT 1", [$nationalId]);
            if ($row) {
                return $row;
            }
        }
        if ($mobile !== '') {
            return Model::fetch("SELECT * FROM users WHERE role = 'customer' AND mobile = ? LIMIT 1", [$mobile]);
        }
        return null;
    }

    protected static function fillCustomerBlanks($id, array $existing, array $incoming)
    {
        $updates = [];
        $params = [];
        foreach (['full_name', 'national_id', 'mobile', 'secondary_phone'] as $field) {
            if (empty($existing[$field]) && !empty($incoming[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $incoming[$field];
            }
        }
        if (!$updates) {
            User::syncCustomerLoginDefaults((int) $id, $existing);
            return;
        }
        $params[] = (int) $id;
        Model::execute('UPDATE users SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?', $params);
        User::syncCustomerLoginDefaults((int) $id);
    }

    protected static function resolveCustomerId(array $row, array $customerMap)
    {
        $sourceId = self::text($row['customer_id'] ?? '');
        if ($sourceId !== '' && !empty($customerMap['source:' . $sourceId])) {
            return (int) $customerMap['source:' . $sourceId];
        }
        $nationalId = self::digits($row['customer_national_id'] ?? $row['national_id'] ?? '');
        if ($nationalId !== '' && !empty($customerMap['national:' . $nationalId])) {
            return (int) $customerMap['national:' . $nationalId];
        }
        $customer = self::findCustomer($nationalId, '');
        return $customer ? (int) $customer['id'] : null;
    }

    protected static function insertContract(array $data, $userId = null)
    {
        $serial = self::nextSerial();
        $prefix = 'IMP';
        while (Model::fetch('SELECT id FROM contracts WHERE prefix = ? AND serial = ?', [$prefix, $serial])) {
            $serial++;
        }
        Model::execute(
            'INSERT INTO contracts
             (customer_id, contract_number, prefix, serial, principal_amount, down_payment_amount, monthly_interest_rate,
              interest_type, months, start_date, first_due_date, status, assigned_operator_id, notes, created_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, NULL, ?, NOW())',
            [
                (int) $data['customer_id'],
                $data['contract_number'],
                $prefix,
                $serial,
                normalize_money($data['principal_amount']),
                'simple',
                (int) $data['months'],
                $data['start_date'],
                $data['first_due_date'],
                $data['status'],
                $data['notes'],
            ]
        );
        $contractId = (int) Model::lastInsertId();
        if (class_exists('ContractDocument')) {
            ContractDocument::log($contractId, 'import_contract', null, $data, 'ورود اطلاعات از Excel', $userId);
        }
        return $contractId;
    }

    protected static function nextSerial()
    {
        $row = Model::fetch('SELECT COALESCE(MAX(serial), 0) + 1 AS next_serial FROM contracts WHERE prefix = ?', ['IMP']);
        return max(1, (int) ($row['next_serial'] ?? 1));
    }

    protected static function syncNextSerial()
    {
        $row = Model::fetch('SELECT COALESCE(MAX(serial), 0) + 1 AS next_serial FROM contracts');
        Settings::set('contract_next_serial', (string) max(1, (int) ($row['next_serial'] ?? 1)));
    }

    protected static function firstDueDate(array $installments)
    {
        $dates = [];
        foreach ($installments as $row) {
            $date = self::parseDate($row['due_date'] ?? '');
            if ($date) {
                $dates[] = $date;
            }
        }
        sort($dates);
        return $dates[0] ?? null;
    }

    protected static function sumInstallments(array $installments)
    {
        $sum = 0;
        foreach ($installments as $row) {
            $sum += normalize_money($row['amount_toman'] ?? $row['amount'] ?? 0);
        }
        return $sum;
    }

    protected static function normalizeContractStatus($status)
    {
        $status = self::text($status);
        if (in_array($status, ['closed', 'inactive'], true)) {
            return $status;
        }
        return 'active';
    }

    protected static function normalizeInstallmentStatus($status, $dueDate)
    {
        $status = self::text($status);
        if (in_array($status, ['paid', 'partial'], true)) {
            return $status;
        }
        return $dueDate < date('Y-m-d') ? 'overdue' : 'pending';
    }

    protected static function parseDate($value)
    {
        return parse_jalali_date($value) ?: null;
    }

    protected static function normalizePhone($value)
    {
        $digits = preg_replace('/\D+/', '', self::digits($value));
        if (strlen($digits) === 10 && strpos($digits, '9') === 0) {
            $digits = '0' . $digits;
        }
        return $digits;
    }

    protected static function digits($value)
    {
        return trim(to_english_digits((string) $value));
    }

    protected static function text($value)
    {
        return trim(to_english_digits((string) $value));
    }
}
