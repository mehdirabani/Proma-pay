<?php

class ContractDocument extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }

        self::execute(
            "CREATE TABLE IF NOT EXISTS contract_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                product_model VARCHAR(190) NOT NULL,
                imei_1 VARCHAR(80) NULL,
                imei_2 VARCHAR(80) NULL,
                description TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_contract_items_contract (contract_id),
                CONSTRAINT fk_contract_items_contract
                    FOREIGN KEY (contract_id) REFERENCES contracts(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::execute(
            "CREATE TABLE IF NOT EXISTS contract_guarantees (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                guarantee_type VARCHAR(50) NOT NULL,
                guarantee_count INT NOT NULL DEFAULT 1,
                guarantee_serial VARCHAR(190) NULL,
                guarantee_description TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_contract_guarantees_contract (contract_id),
                CONSTRAINT fk_contract_guarantees_contract
                    FOREIGN KEY (contract_id) REFERENCES contracts(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::execute(
            "CREATE TABLE IF NOT EXISTS contract_guarantor_people (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                full_name VARCHAR(190) NOT NULL,
                father_name VARCHAR(190) NULL,
                national_id VARCHAR(20) NULL,
                mobile VARCHAR(30) NULL,
                address TEXT NULL,
                relationship VARCHAR(100) NULL,
                description TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_contract_guarantor_people_contract (contract_id),
                CONSTRAINT fk_contract_guarantor_people_contract
                    FOREIGN KEY (contract_id) REFERENCES contracts(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::execute(
            "CREATE TABLE IF NOT EXISTS generated_contract_documents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                rendered_body LONGTEXT NOT NULL,
                generated_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_generated_contract (contract_id),
                CONSTRAINT fk_generated_contract_documents_contract
                    FOREIGN KEY (contract_id) REFERENCES contracts(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::execute(
            "CREATE TABLE IF NOT EXISTS contract_change_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                changed_by BIGINT UNSIGNED NULL,
                change_type VARCHAR(80) NOT NULL,
                old_value_json LONGTEXT NULL,
                new_value_json LONGTEXT NULL,
                reason TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_contract_change_logs_contract (contract_id),
                CONSTRAINT fk_contract_change_logs_contract
                    FOREIGN KEY (contract_id) REFERENCES contracts(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        try {
            self::execute('ALTER TABLE installments ADD COLUMN guarantee_serial VARCHAR(190) NULL AFTER notes');
        } catch (Throwable $e) {
        }

        self::$schemaReady = true;
    }

    public static function variables()
    {
        return [
            '{{contract_number}}',
            '{{contract_date}}',
            '{{company_name}}',
            '{{company_representative_name}}',
            '{{company_representative_national_id}}',
            '{{company_address}}',
            '{{company_postal_code}}',
            '{{company_phone}}',
            '{{customer_full_name}}',
            '{{customer_father_name}}',
            '{{customer_national_id}}',
            '{{customer_issued_from}}',
            '{{customer_mobile}}',
            '{{customer_secondary_phone}}',
            '{{customer_address}}',
            '{{items_table}}',
            '{{installments_guarantees_table}}',
            '{{guarantors_section}}',
            '{{guarantee_type}}',
            '{{guarantee_count}}',
            '{{guarantee_serial}}',
            '{{guarantee_description}}',
            '{{installment_count}}',
            '{{total_contract_amount}}',
            '{{down_payment_amount}}',
            '{{remaining_amount}}',
            '{{monthly_penalty_rate}}',
            '{{first_due_date}}',
            '{{last_due_date}}',
            '{{signature_section}}',
        ];
    }

    public static function defaultTemplate()
    {
        return <<<'TEXT'
قرارداد اجاره به شرط تملیک

این قرارداد فیمابین:

موجر: {{company_representative_name}} به شماره ملی {{company_representative_national_id}} به نشانی {{company_address}}، کدپستی {{company_postal_code}} که از این پس "موبایل پروما" نامیده می‌شود.

مستأجر: اینجانب {{customer_full_name}} فرزند {{customer_father_name}} به شماره ملی {{customer_national_id}} صادره از {{customer_issued_from}}، به شماره تماس {{customer_mobile}} و {{customer_secondary_phone}} ساکن {{customer_address}} که از این پس "امانت‌دار" نامیده می‌شود، منعقد گردید.

ماده ۱ - مشخصات کالای امانت

موبایل پروما، کالاهای مشخص‌شده در جدول زیر را در تاریخ تنظیم قرارداد به صورت امانت در اختیار امانت‌دار قرار می‌دهد که از این پس "کالای امانت" نامیده می‌شود:

{{items_table}}

ماده ۲ - شرایط ضمانت و پرداخت اقساط

امانت‌دار تعداد {{guarantee_count}} {{guarantee_type}} نزد موبایل پروما به عنوان ضمانت کالای امانت گرو گذاشته و متعهد می‌شود مطابق جدول زیر اقساط تعیین‌شده را پرداخت نماید:

{{installments_guarantees_table}}

{{guarantors_section}}

ماده ۳ - تعهدات امانت‌دار

- امانت‌دار متعهد است کالای امانت را با رعایت اصول نگهداری، در شرایط سالم و بدون آسیب حفظ کند.
- امانت‌دار موظف است اقساط تعیین‌شده را در موعد مقرر پرداخت نماید.
- در صورت عدم پرداخت اقساط، مشمول جرایم مقرر در ماده ۴ خواهد شد.
- در صورت مفقودی یا سرقت کالای امانت، امانت‌دار موظف است ظرف ۴۸ ساعت مراتب را به موبایل پروما و مراجع قانونی اطلاع دهد و ادامه پرداخت اقساط طبق قرارداد الزامی است.

ماده ۴ - شرایط تأخیر در پرداخت و عواقب آن

- در صورت تأخیر در پرداخت هر قسط، بابت هر ماه دیرکرد، {{monthly_penalty_rate}} درصد مرکب از مبلغ قسط به عنوان جریمه تأخیر اضافه می‌شود.
- در صورت تأخیر بیش از ۲۰ روز، موبایل پروما مجاز است کالای امانت را بازپس گیرد و ضمانت ارائه‌شده را وصول نماید.
- اگر ظرف ۷ روز پس از اخطار رسمی، کالای امانت در شرایط اولیه بازگردانده نشود، موبایل پروما حق شکایت و اعلام سرقت را دارد.
- در صورت نقص یا خسارت به کالای امانت، امانت‌دار موظف به جبران خسارت طبق نظر کارشناس رسمی می‌باشد.
- در صورت بازپس‌گیری کالا، تمامی مبالغ پرداخت‌شده نزد موبایل پروما باقی می‌ماند و امانت‌دار حق اعتراض ندارد.
- در صورت اقامه دعوی توسط موبایل پروما، تمامی هزینه‌های قانونی و دادرسی بر عهده امانت‌دار خواهد بود.

ماده ۵ - تملک کالا پس از پرداخت اقساط

پس از پرداخت کلیه اقساط، امانت‌دار با مراجعه به موبایل پروما می‌تواند کالا را به طور کامل خریداری نموده و مدارک ضمانتی خود را بازپس گیرد. اگر ضمانت از نوع چک باشد، چک وصول خواهد شد و پس از تملک کالا، چک عودت داده نمی‌شود.

ماده ۶ - فسخ قرارداد

در صورت عدم پرداخت قسط توسط مستأجر، با رعایت مهلت ۷ روز پس از اخطار، موجر حق فسخ یک‌جانبه قرارداد را خواهد داشت.

کلیه اختلافات ناشی از اجرا یا تفسیر این قرارداد، در مرحله اول از طریق سازش و مذاکره و در صورت عدم حصول نتیجه، در دادگاه‌های عمومی آشخانه قابل پیگیری خواهد بود.

{{signature_section}}
TEXT;
    }

    public static function items($contractId)
    {
        self::ensureSchema();
        return self::fetchAll('SELECT * FROM contract_items WHERE contract_id = ? ORDER BY id', [(int) $contractId]);
    }

    public static function guarantees($contractId)
    {
        self::ensureSchema();
        return self::fetchAll('SELECT * FROM contract_guarantees WHERE contract_id = ? ORDER BY id', [(int) $contractId]);
    }

    public static function guarantorPeople($contractId)
    {
        self::ensureSchema();
        return self::fetchAll('SELECT * FROM contract_guarantor_people WHERE contract_id = ? ORDER BY id', [(int) $contractId]);
    }

    public static function document($contractId)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM generated_contract_documents WHERE contract_id = ?', [(int) $contractId]);
    }

    public static function logs($contractId)
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT l.*, u.full_name AS changed_by_name
             FROM contract_change_logs l
             LEFT JOIN users u ON u.id = l.changed_by
             WHERE l.contract_id = ?
             ORDER BY l.id DESC",
            [(int) $contractId]
        );
    }

    public static function saveItems($contractId, array $items)
    {
        self::ensureSchema();
        self::execute('DELETE FROM contract_items WHERE contract_id = ?', [(int) $contractId]);
        foreach ($items as $item) {
            $model = trim((string) ($item['product_model'] ?? ''));
            if ($model === '') {
                continue;
            }
            self::execute(
                'INSERT INTO contract_items (contract_id, product_model, imei_1, imei_2, description, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    (int) $contractId,
                    $model,
                    trim(to_english_digits($item['imei_1'] ?? '')) ?: null,
                    trim(to_english_digits($item['imei_2'] ?? '')) ?: null,
                    trim((string) ($item['description'] ?? '')) ?: null,
                ]
            );
        }
    }

    public static function saveGuarantee($contractId, array $data)
    {
        self::ensureSchema();
        self::execute('DELETE FROM contract_guarantees WHERE contract_id = ?', [(int) $contractId]);
        $type = trim((string) ($data['guarantee_type'] ?? ''));
        if ($type === '') {
            return;
        }
        $description = trim((string) ($data['guarantee_description'] ?? ''));
        $other = trim((string) ($data['guarantee_type_other'] ?? ''));
        if ($type === 'سایر' && $other !== '') {
            $description = 'نوع ضمانت: ' . $other . ($description !== '' ? "\n" . $description : '');
        }
        self::execute(
            'INSERT INTO contract_guarantees (contract_id, guarantee_type, guarantee_count, guarantee_serial, guarantee_description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                (int) $contractId,
                $type,
                max(1, (int) to_english_digits($data['guarantee_count'] ?? 1)),
                trim(to_english_digits($data['guarantee_serial'] ?? '')) ?: null,
                $description ?: null,
            ]
        );
    }

    public static function saveGuarantorPeople($contractId, array $people)
    {
        self::ensureSchema();
        self::execute('DELETE FROM contract_guarantor_people WHERE contract_id = ?', [(int) $contractId]);
        foreach ($people as $person) {
            $name = trim((string) ($person['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            self::execute(
                'INSERT INTO contract_guarantor_people
                 (contract_id, full_name, father_name, national_id, mobile, address, relationship, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    (int) $contractId,
                    $name,
                    trim((string) ($person['father_name'] ?? '')) ?: null,
                    trim(to_english_digits($person['national_id'] ?? '')) ?: null,
                    trim(to_english_digits($person['mobile'] ?? '')) ?: null,
                    trim((string) ($person['address'] ?? '')) ?: null,
                    trim((string) ($person['relationship'] ?? '')) ?: null,
                    trim((string) ($person['description'] ?? '')) ?: null,
                ]
            );
        }
    }

    public static function generate($contractId, $generatedBy = null)
    {
        self::ensureSchema();
        $rendered = self::render((int) $contractId);
        $existing = self::document((int) $contractId);
        self::execute(
            'INSERT INTO generated_contract_documents (contract_id, rendered_body, generated_by, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NULL)
             ON DUPLICATE KEY UPDATE rendered_body = VALUES(rendered_body), generated_by = VALUES(generated_by), updated_at = NOW()',
            [(int) $contractId, $rendered, $generatedBy ? (int) $generatedBy : null]
        );
        self::log(
            (int) $contractId,
            $existing ? 'regenerate_document' : 'generate_document',
            $existing ? ['rendered_body' => $existing['rendered_body']] : null,
            ['rendered_body' => $rendered],
            $existing ? 'تولید مجدد متن قرارداد' : 'تولید متن قرارداد',
            $generatedBy
        );
        return $rendered;
    }

    public static function saveRenderedBody($contractId, $body, $userId, $reason)
    {
        self::ensureSchema();
        $body = trim((string) $body);
        if ($body === '') {
            throw new InvalidArgumentException('متن قرارداد نمی‌تواند خالی باشد.');
        }
        $existing = self::document((int) $contractId);
        self::execute(
            'INSERT INTO generated_contract_documents (contract_id, rendered_body, generated_by, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NULL)
             ON DUPLICATE KEY UPDATE rendered_body = VALUES(rendered_body), generated_by = VALUES(generated_by), updated_at = NOW()',
            [(int) $contractId, $body, $userId ? (int) $userId : null]
        );
        self::log(
            (int) $contractId,
            'edit_document',
            $existing ? ['rendered_body' => $existing['rendered_body']] : null,
            ['rendered_body' => $body],
            $reason ?: 'ویرایش دستی متن قرارداد',
            $userId
        );
    }

    public static function render($contractId)
    {
        $contract = Contract::find((int) $contractId);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }
        $settings = Settings::allKeyed();
        $template = trim((string) ($settings['contract_template_body'] ?? ''));
        if ($template === '') {
            $template = self::defaultTemplate();
        }
        $items = self::items((int) $contractId);
        $guarantees = self::guarantees((int) $contractId);
        $guarantorPeople = self::guarantorPeople((int) $contractId);
        $installments = Installment::all(['contract_id' => (int) $contractId]);
        $firstGuarantee = $guarantees[0] ?? [];
        $lastInstallment = $installments ? end($installments) : null;
        $remaining = max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0));

        $html = nl2br(e($template), false);
        $replace = [
            '{{contract_number}}' => e($contract['contract_number']),
            '{{contract_date}}' => e(jdate($contract['start_date'])),
            '{{company_name}}' => e($settings['company_name'] ?? ''),
            '{{company_representative_name}}' => e($settings['company_representative_name'] ?? ''),
            '{{company_representative_national_id}}' => e(to_persian_digits($settings['company_representative_national_id'] ?? '')),
            '{{company_address}}' => e($settings['company_address'] ?? ''),
            '{{company_postal_code}}' => e(to_persian_digits($settings['company_postal_code'] ?? '')),
            '{{company_phone}}' => e(to_persian_digits($settings['company_phone'] ?? '')),
            '{{customer_full_name}}' => e($contract['customer_name'] ?? ''),
            '{{customer_father_name}}' => e($contract['customer_father_name'] ?? ''),
            '{{customer_national_id}}' => e(to_persian_digits($contract['national_id'] ?? '')),
            '{{customer_issued_from}}' => e($contract['customer_issued_from'] ?? ''),
            '{{customer_mobile}}' => e(to_persian_digits($contract['mobile'] ?? '')),
            '{{customer_secondary_phone}}' => e(to_persian_digits($contract['secondary_phone'] ?? '')),
            '{{customer_address}}' => e($contract['customer_address'] ?? ''),
            '{{items_table}}' => self::itemsTable($items),
            '{{installments_guarantees_table}}' => self::installmentsGuaranteesTable($installments, $firstGuarantee),
            '{{guarantors_section}}' => self::guarantorsSection($guarantorPeople),
            '{{guarantee_type}}' => e($firstGuarantee['guarantee_type'] ?? ''),
            '{{guarantee_count}}' => e(to_persian_digits($firstGuarantee['guarantee_count'] ?? 0)),
            '{{guarantee_serial}}' => e(to_persian_digits($firstGuarantee['guarantee_serial'] ?? '')),
            '{{guarantee_description}}' => e($firstGuarantee['guarantee_description'] ?? ''),
            '{{installment_count}}' => e(to_persian_digits(count($installments))),
            '{{total_contract_amount}}' => money_toman($contract['principal_amount']),
            '{{down_payment_amount}}' => money_toman($contract['down_payment_amount'] ?? 0),
            '{{remaining_amount}}' => money_toman($remaining),
            '{{monthly_penalty_rate}}' => e(to_persian_digits($settings['monthly_penalty_rate'] ?? $contract['monthly_interest_rate'] ?? '0')),
            '{{first_due_date}}' => e(jdate($contract['first_due_date'])),
            '{{last_due_date}}' => e($lastInstallment ? jdate($lastInstallment['due_date']) : ''),
            '{{signature_section}}' => self::signatureSection($guarantorPeople),
        ];
        foreach ($replace as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }
        return '<div class="contract-document-body">' . $html . '</div>';
    }

    public static function log($contractId, $type, $oldValue = null, $newValue = null, $reason = '', $userId = null)
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO contract_change_logs (contract_id, changed_by, change_type, old_value_json, new_value_json, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                (int) $contractId,
                $userId ? (int) $userId : null,
                $type,
                $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE),
                $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE),
                trim((string) $reason) ?: null,
            ]
        );
    }

    protected static function itemsTable(array $items)
    {
        if (!$items) {
            return '<div class="contract-empty">کالایی برای این قرارداد ثبت نشده است.</div>';
        }
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td>' . e($item['product_model']) . '</td><td dir="ltr">' . e(to_persian_digits($item['imei_1'] ?? '')) . '</td><td dir="ltr">' . e(to_persian_digits($item['imei_2'] ?? '')) . '</td></tr>';
        }
        return '<table class="contract-print-table"><thead><tr><th>مدل کالا</th><th>IMEI 1</th><th>IMEI 2</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    protected static function installmentsGuaranteesTable(array $installments, array $guarantee)
    {
        if (!$installments) {
            return '<div class="contract-empty">قسطی برای این قرارداد ثبت نشده است.</div>';
        }
        $defaultSerial = $guarantee['guarantee_serial'] ?? '';
        $rows = '';
        foreach ($installments as $installment) {
            $serial = $installment['guarantee_serial'] ?? $defaultSerial;
            $rows .= '<tr><td dir="ltr">' . e(to_persian_digits($serial)) . '</td><td>' . e(jdate($installment['due_date'])) . '</td><td>' . money_toman($installment['base_amount']) . '</td></tr>';
        }
        return '<table class="contract-print-table"><thead><tr><th>شناسه چک / سفته</th><th>تاریخ پرداخت</th><th>مبلغ پرداخت</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    protected static function guarantorsSection(array $people)
    {
        if (!$people) {
            return '';
        }
        $html = '<section class="contract-guarantors-section"><h3>مشخصات ضامن‌ها</h3>';
        foreach ($people as $person) {
            $html .= '<div class="contract-guarantor-box">'
                . '<strong>' . e($person['full_name']) . '</strong>'
                . '<span>فرزند: ' . e($person['father_name'] ?? '') . '</span>'
                . '<span>کد ملی: ' . e(to_persian_digits($person['national_id'] ?? '')) . '</span>'
                . '<span>تماس: ' . e(to_persian_digits($person['mobile'] ?? '')) . '</span>'
                . '<span>نسبت: ' . e($person['relationship'] ?? '') . '</span>'
                . '<span class="full">آدرس: ' . e($person['address'] ?? '') . '</span>'
                . '</div>';
        }
        return $html . '</section>';
    }

    protected static function signatureSection(array $people)
    {
        $labels = ['امضای موبایل پروما', 'امضای امانت‌دار'];
        foreach ($people as $index => $person) {
            $labels[] = count($people) === 1 ? 'امضای ضامن' : 'امضای ضامن ' . to_persian_digits($index + 1);
        }
        $html = '<section class="contract-signature-grid">';
        foreach ($labels as $label) {
            $html .= '<div class="contract-signature-box"><span>' . e($label) . '</span></div>';
        }
        return $html . '</section>';
    }
}
