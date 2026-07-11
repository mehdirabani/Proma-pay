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
                rendered_title VARCHAR(190) NULL,
                rendered_header TEXT NULL,
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
        try {
            self::execute('ALTER TABLE generated_contract_documents ADD COLUMN rendered_title VARCHAR(190) NULL AFTER contract_id');
        } catch (Throwable $e) {
        }
        try {
            self::execute('ALTER TABLE generated_contract_documents ADD COLUMN rendered_header TEXT NULL AFTER rendered_title');
        } catch (Throwable $e) {
        }

        self::$schemaReady = true;
    }

    public static function variables()
    {
        return array_keys(self::variableDescriptions());
    }

    public static function variableDescriptions()
    {
        return [
            '{{contract_number}}' => 'شماره قرارداد',
            '{{contract_date}}' => 'تاریخ قرارداد',
            '{{document_title}}' => 'عنوان چاپی قرارداد',
            '{{document_header}}' => 'هدر چاپی قرارداد',
            '{{company_name}}' => 'نام مجموعه',
            '{{company_representative_name}}' => 'نام نماینده مجموعه',
            '{{company_representative_national_id}}' => 'کد ملی نماینده مجموعه',
            '{{company_address}}' => 'آدرس مجموعه',
            '{{company_postal_code}}' => 'کد پستی مجموعه',
            '{{company_phone}}' => 'شماره تماس مجموعه',
            '{{customer_full_name}}' => 'نام و نام خانوادگی مشتری',
            '{{customer_father_name}}' => 'نام پدر مشتری',
            '{{customer_national_id}}' => 'کد ملی مشتری',
            '{{customer_issued_from}}' => 'محل صدور مشتری',
            '{{customer_mobile}}' => 'شماره موبایل مشتری',
            '{{customer_secondary_phone}}' => 'شماره تماس دوم مشتری',
            '{{customer_address}}' => 'آدرس مشتری',
            '{{items_table}}' => 'جدول کالاهای قرارداد',
            '{{installments_guarantees_table}}' => 'جدول اقساط و ضمانت',
            '{{guarantors_section}}' => 'بخش مشخصات ضامن‌ها',
            '{{guarantee_type}}' => 'نوع ضمانت',
            '{{guarantee_count}}' => 'تعداد ضمانت',
            '{{guarantee_serial}}' => 'شناسه چک یا سفته',
            '{{guarantee_description}}' => 'توضیحات ضمانت',
            '{{installment_count}}' => 'تعداد اقساط',
            '{{total_contract_amount}}' => 'مبلغ اصل قرارداد',
            '{{down_payment_amount}}' => 'مبلغ پیش‌پرداخت',
            '{{remaining_amount}}' => 'مانده قابل تقسیط',
            '{{monthly_penalty_rate}}' => 'نرخ جریمه عادی ماهانه',
            '{{legal_monthly_penalty_rate}}' => 'نرخ جریمه حقوقی ماهانه',
            '{{late_penalty_grace_days}}' => 'مدت تنفس دیرکرد به روز',
            '{{legal_penalty_clause}}' => 'متن تایید جریمه حقوقی',
            '{{first_due_date}}' => 'تاریخ اولین سررسید',
            '{{last_due_date}}' => 'تاریخ آخرین سررسید',
            '{{signature_section}}' => 'محل امضاها',
        ];
    }

    public static function defaultTemplate()
    {
        return <<<'TEXT'
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

- در صورت تأخیر در پرداخت هر قسط، تا پیش از ارجاع یا ثبت پرونده حقوقی، بابت هر ماه دیرکرد، {{monthly_penalty_rate}} درصد از مانده قسط به عنوان جریمه تأخیر عادی محاسبه می‌شود.
- در صورت ورود قرارداد به مرحله حقوقی یا شکایت، از همان تاریخ به بعد بابت هر ماه دیرکرد، {{legal_monthly_penalty_rate}} مرکب درصد از مانده قسط به عنوان جریمه تأخیر حقوقی محاسبه می‌شود.
- تا {{late_penalty_grace_days}} روز پس از سررسید هر قسط، جریمه دیرکرد محاسبه نمی‌شود و پس از پایان این مهلت، جریمه از روز بعد محاسبه خواهد شد.
- {{legal_penalty_clause}}
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
        try {
            $version = self::fetch(
                'SELECT id AS document_version_id, contract_id, version_number, rendered_title, rendered_header, rendered_body, source, checksum, is_published, is_finalized, generated_by, created_at
                 FROM contract_document_versions
                 WHERE contract_id = ? AND is_published = 1
                 ORDER BY version_number DESC, id DESC LIMIT 1',
                [(int) $contractId]
            );
            if ($version) {
                return $version;
            }
        } catch (Throwable $e) {
            if (!self::missingVersionTable($e)) {
                throw $e;
            }
        }
        return self::fetch('SELECT * FROM generated_contract_documents WHERE contract_id = ?', [(int) $contractId]);
    }

    public static function versions($contractId)
    {
        self::ensureSchema();
        try {
            return self::fetchAll('SELECT v.*, u.full_name AS generated_by_name FROM contract_document_versions v LEFT JOIN users u ON u.id = v.generated_by WHERE v.contract_id = ? ORDER BY v.version_number DESC, v.id DESC', [(int) $contractId]);
        } catch (Throwable $e) {
            if (!self::missingVersionTable($e)) {
                throw $e;
            }
            return [];
        }
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
        $title = self::renderTitle((int) $contractId);
        $header = self::renderHeader((int) $contractId);
        $existing = self::document((int) $contractId);
        self::execute(
            'INSERT INTO generated_contract_documents (contract_id, rendered_title, rendered_header, rendered_body, generated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NULL)
             ON DUPLICATE KEY UPDATE rendered_title = VALUES(rendered_title), rendered_header = VALUES(rendered_header), rendered_body = VALUES(rendered_body), generated_by = VALUES(generated_by), updated_at = NOW()',
            [(int) $contractId, $title, $header, $rendered, $generatedBy ? (int) $generatedBy : null]
        );
        self::recordVersion((int) $contractId, $title, $header, $rendered, 'generated', $generatedBy);
        self::log(
            (int) $contractId,
            $existing ? 'regenerate_document' : 'generate_document',
            $existing ? ['rendered_body' => $existing['rendered_body']] : null,
            ['rendered_title' => $title, 'rendered_header' => $header, 'rendered_body' => $rendered],
            $existing ? 'تولید مجدد متن قرارداد' : 'تولید متن قرارداد',
            $generatedBy
        );
        return $rendered;
    }

    public static function saveRenderedBody($contractId, $body, $userId, $reason, $title = null, $header = null)
    {
        self::ensureSchema();
        $body = trim((string) $body);
        if ($body === '') {
            throw new InvalidArgumentException('متن قرارداد نمی‌تواند خالی باشد.');
        }
        $body = self::sanitizeHtml($body);
        $title = trim((string) ($title ?? '')) ?: self::renderTitle((int) $contractId);
        $header = trim((string) ($header ?? '')) ?: self::renderHeader((int) $contractId);
        $existing = self::document((int) $contractId);
        self::execute(
            'INSERT INTO generated_contract_documents (contract_id, rendered_title, rendered_header, rendered_body, generated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NULL)
             ON DUPLICATE KEY UPDATE rendered_title = VALUES(rendered_title), rendered_header = VALUES(rendered_header), rendered_body = VALUES(rendered_body), generated_by = VALUES(generated_by), updated_at = NOW()',
            [(int) $contractId, $title, $header, $body, $userId ? (int) $userId : null]
        );
        self::recordVersion((int) $contractId, $title, $header, $body, 'manual', $userId);
        self::log(
            (int) $contractId,
            'edit_document',
            $existing ? ['rendered_body' => $existing['rendered_body']] : null,
            ['rendered_title' => $title, 'rendered_header' => $header, 'rendered_body' => $body],
            $reason ?: 'ویرایش دستی متن قرارداد',
            $userId
        );
    }

    public static function publishVersion($versionId, $userId = null)
    {
        self::ensureSchema();
        $version = self::fetch('SELECT * FROM contract_document_versions WHERE id = ? LIMIT 1', [(int) $versionId]);
        if (!$version) {
            throw new InvalidArgumentException('نسخه قرارداد پیدا نشد.');
        }
        self::begin();
        try {
            self::execute('UPDATE contract_document_versions SET is_published = 0 WHERE contract_id = ?', [(int) $version['contract_id']]);
            self::execute('UPDATE contract_document_versions SET is_published = 1 WHERE id = ?', [(int) $versionId]);
            self::log((int) $version['contract_id'], 'publish_document_version', null, ['version_id' => (int) $versionId], 'انتشار نسخه قرارداد', $userId);
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function finalizeVersion($versionId, $userId = null)
    {
        self::ensureSchema();
        $version = self::fetch('SELECT * FROM contract_document_versions WHERE id = ? LIMIT 1', [(int) $versionId]);
        if (!$version) {
            throw new InvalidArgumentException('نسخه قرارداد پیدا نشد.');
        }
        self::begin();
        try {
            self::execute('UPDATE contract_document_versions SET is_published = 0 WHERE contract_id = ?', [(int) $version['contract_id']]);
            self::execute('UPDATE contract_document_versions SET is_published = 1, is_finalized = 1 WHERE id = ?', [(int) $versionId]);
            self::log((int) $version['contract_id'], 'finalize_document_version', null, ['version_id' => (int) $versionId], 'نهایی‌سازی نسخه قرارداد', $userId);
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    protected static function recordVersion($contractId, $title, $header, $body, $source, $userId = null)
    {
        try {
            $checksum = hash('sha256', (string) $title . "\0" . (string) $header . "\0" . (string) $body);
            $latest = self::fetch('SELECT * FROM contract_document_versions WHERE contract_id = ? ORDER BY version_number DESC, id DESC LIMIT 1', [(int) $contractId]);
            if ($latest && hash_equals((string) $latest['checksum'], $checksum)) {
                return (int) $latest['id'];
            }
            $versionNumber = ((int) ($latest['version_number'] ?? 0)) + 1;
            $finalized = self::fetch('SELECT id FROM contract_document_versions WHERE contract_id = ? AND is_finalized = 1 LIMIT 1', [(int) $contractId]);
            $published = $finalized ? 0 : 1;
            if ($published) {
                self::execute('UPDATE contract_document_versions SET is_published = 0 WHERE contract_id = ?', [(int) $contractId]);
            }
            self::execute(
                'INSERT INTO contract_document_versions (contract_id, version_number, rendered_title, rendered_header, rendered_body, source, checksum, is_published, is_finalized, generated_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())',
                [(int) $contractId, $versionNumber, $title, $header, $body, trim((string) $source) ?: 'generated', $checksum, $published, $userId ? (int) $userId : null]
            );
            return (int) self::lastInsertId();
        } catch (Throwable $e) {
            if (!self::missingVersionTable($e)) {
                throw $e;
            }
            return null;
        }
    }

    protected static function missingVersionTable(Throwable $e)
    {
        return $e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1146;
    }

    public static function renderTitle($contractId)
    {
        return self::renderPlainTemplate((int) $contractId, 'contract_document_title', 'قرارداد اجاره به شرط تملیک / امانت‌داری');
    }

    public static function renderHeader($contractId)
    {
        return self::renderPlainTemplate((int) $contractId, 'contract_document_header', "{{company_name}}\nشماره قرارداد: {{contract_number}} | تاریخ: {{contract_date}}\nامانت‌دار: {{customer_full_name}} | کد ملی: {{customer_national_id}}");
    }

    protected static function renderPlainTemplate($contractId, $settingKey, $fallback)
    {
        $contract = Contract::find((int) $contractId);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }
        $settings = Settings::allKeyed();
        $template = trim((string) ($settings[$settingKey] ?? '')) ?: $fallback;
        return trim(strtr($template, self::plainReplacements($contract, $settings)));
    }

    public static function previewTemplateHtml($template)
    {
        $template = trim((string) $template);
        if ($template === '') {
            $template = self::defaultTemplate();
        }
        return '<div class="contract-document-body">' . self::templateToHtml($template) . '</div>';
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
        $legalPenaltyClause = self::legalPenaltyClause($settings);

        $html = self::templateToHtml($template);
        $replace = [
            '{{contract_number}}' => e($contract['contract_number']),
            '{{contract_date}}' => e(jdate($contract['start_date'])),
            '{{document_title}}' => e(self::renderTitle((int) $contractId)),
            '{{document_header}}' => nl2br(e(self::renderHeader((int) $contractId)), false),
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
            '{{legal_monthly_penalty_rate}}' => e(to_persian_digits($settings['legal_monthly_penalty_rate'] ?? $settings['monthly_penalty_rate'] ?? '0')),
            '{{late_penalty_grace_days}}' => e(to_persian_digits($settings['late_penalty_grace_days'] ?? '0')),
            '{{legal_penalty_clause}}' => e($legalPenaltyClause),
            '{{first_due_date}}' => e(jdate($contract['first_due_date'])),
            '{{last_due_date}}' => e($lastInstallment ? jdate($lastInstallment['due_date']) : ''),
            '{{signature_section}}' => self::signatureSection($guarantorPeople),
        ];
        foreach ($replace as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }
        if (!self::templateContainsLegalPenalty($template)) {
            $html .= '<br><br>' . nl2br(e($legalPenaltyClause), false);
        }
        return '<div class="contract-document-body">' . $html . '</div>';
    }

    protected static function plainReplacements(array $contract, array $settings)
    {
        return [
            '{{contract_number}}' => (string) ($contract['contract_number'] ?? ''),
            '{{contract_date}}' => jdate($contract['start_date'] ?? date('Y-m-d')),
            '{{company_name}}' => (string) ($settings['company_name'] ?? ''),
            '{{company_representative_name}}' => (string) ($settings['company_representative_name'] ?? ''),
            '{{company_representative_national_id}}' => to_persian_digits($settings['company_representative_national_id'] ?? ''),
            '{{company_address}}' => (string) ($settings['company_address'] ?? ''),
            '{{company_postal_code}}' => to_persian_digits($settings['company_postal_code'] ?? ''),
            '{{company_phone}}' => to_persian_digits($settings['company_phone'] ?? ''),
            '{{customer_full_name}}' => (string) ($contract['customer_name'] ?? ''),
            '{{customer_father_name}}' => (string) ($contract['customer_father_name'] ?? ''),
            '{{customer_national_id}}' => to_persian_digits($contract['national_id'] ?? ''),
            '{{customer_issued_from}}' => (string) ($contract['customer_issued_from'] ?? ''),
            '{{customer_mobile}}' => to_persian_digits($contract['mobile'] ?? ''),
            '{{customer_secondary_phone}}' => to_persian_digits($contract['secondary_phone'] ?? ''),
            '{{customer_address}}' => (string) ($contract['customer_address'] ?? ''),
            '{{monthly_penalty_rate}}' => to_persian_digits($settings['monthly_penalty_rate'] ?? '0'),
            '{{legal_monthly_penalty_rate}}' => to_persian_digits($settings['legal_monthly_penalty_rate'] ?? $settings['monthly_penalty_rate'] ?? '0'),
            '{{late_penalty_grace_days}}' => to_persian_digits($settings['late_penalty_grace_days'] ?? '0'),
        ];
    }

    protected static function legalPenaltyClause(array $settings)
    {
        $clause = trim((string) ($settings['contract_legal_penalty_clause'] ?? ''));
        if ($clause === '') {
            $clause = 'اینجانب امانت‌دار اعلام می‌کنم بند جریمه دیرکرد عادی و جریمه دیرکرد مرحله حقوقی را مطالعه کرده و می‌پذیرم. تا پیش از ثبت یا ارجاع پرونده حقوقی، جریمه دیرکرد با نرخ عادی ماهانه محاسبه می‌شود؛ از زمان ورود قرارداد به مرحله حقوقی یا شکایت، جریمه دیرکرد با نرخ حقوقی ماهانه محاسبه خواهد شد.';
        }
        return strtr($clause, [
            '{{monthly_penalty_rate}}' => to_persian_digits($settings['monthly_penalty_rate'] ?? '0'),
            '{{legal_monthly_penalty_rate}}' => to_persian_digits($settings['legal_monthly_penalty_rate'] ?? $settings['monthly_penalty_rate'] ?? '0'),
            '{{late_penalty_grace_days}}' => to_persian_digits($settings['late_penalty_grace_days'] ?? '0'),
        ]);
    }

    protected static function templateContainsLegalPenalty($template)
    {
        return strpos((string) $template, '{{legal_monthly_penalty_rate}}') !== false
            || strpos((string) $template, '{{legal_penalty_clause}}') !== false;
    }

    protected static function templateToHtml($template)
    {
        $template = self::decodeHtmlEntities(trim((string) $template));
        if ($template === '') {
            return '';
        }
        if (!self::looksLikeHtml($template)) {
            return nl2br(e($template), false);
        }
        return self::sanitizeHtml($template);
    }

    public static function sanitizeHtml($html)
    {
        $html = self::decodeHtmlEntities(trim((string) $html));
        if ($html === '') {
            return '';
        }
        if (!self::looksLikeHtml($html)) {
            return nl2br(e($html), false);
        }

        $allowedTags = '<p><br><div><span><strong><b><em><i><u><s><ul><ol><li><blockquote><h1><h2><h3><h4><table><thead><tbody><tfoot><tr><th><td><small><sup><sub><a><pre><code>';
        $html = strip_tags($html, $allowedTags);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+(src|srcset)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+(?!href\b|style\b|class\b|dir\b|target\b|rel\b|colspan\b|rowspan\b)[a-z0-9_:-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace_callback('/\s+href\s*=\s*([\'"])(.*?)\1/is', function ($match) {
            $href = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $href)) {
                return '';
            }
            if (!preg_match('#^(https?://|mailto:|tel:|#|/)#i', $href)) {
                return '';
            }
            return ' href="' . e($href) . '"';
        }, $html);
        $html = preg_replace_callback('/\s+style\s*=\s*([\'"])(.*?)\1/is', function ($match) {
            $style = self::sanitizeInlineStyle($match[2]);
            return $style === '' ? '' : ' style="' . e($style) . '"';
        }, $html);
        $html = preg_replace_callback('/\s+class\s*=\s*([\'"])(.*?)\1/is', function ($match) {
            $classes = preg_split('/\s+/', trim((string) $match[2])) ?: [];
            $classes = array_values(array_filter($classes, function ($class) {
                return preg_match('/^[a-z0-9_-]{1,48}$/i', $class);
            }));
            return $classes ? ' class="' . e(implode(' ', array_slice($classes, 0, 12))) . '"' : '';
        }, $html);
        $html = preg_replace_callback('/\s+(dir|target|rel|colspan|rowspan)\s*=\s*([\'"])(.*?)\2/is', function ($match) {
            $name = strtolower($match[1]);
            $value = trim((string) $match[3]);
            if ($name === 'dir' && !in_array($value, ['rtl', 'ltr', 'auto'], true)) {
                return '';
            }
            if ($name === 'target') {
                return $value === '_blank' ? ' target="_blank"' : '';
            }
            if ($name === 'rel') {
                return ' rel="noopener noreferrer"';
            }
            if (in_array($name, ['colspan', 'rowspan'], true)) {
                $number = max(1, min(12, (int) to_english_digits($value)));
                return ' ' . $name . '="' . $number . '"';
            }
            return '';
        }, $html);

        return trim($html);
    }

    protected static function sanitizeInlineStyle($style)
    {
        $allowed = [
            'color',
            'background-color',
            'text-align',
            'direction',
            'font-weight',
            'font-style',
            'text-decoration',
        ];
        $safe = [];
        foreach (explode(';', (string) $style) as $rule) {
            if (!preg_match('/^\s*([a-z-]+)\s*:\s*([^;]+)\s*$/i', $rule, $match)) {
                continue;
            }
            $property = strtolower($match[1]);
            $value = trim($match[2]);
            if (!in_array($property, $allowed, true) || preg_match('/url\s*\(|expression\s*\(|javascript:|behavior\s*:/i', $value)) {
                continue;
            }
            if ($property === 'text-align' && !in_array(strtolower($value), ['right', 'left', 'center', 'justify'], true)) {
                continue;
            }
            if ($property === 'direction' && !in_array(strtolower($value), ['rtl', 'ltr'], true)) {
                continue;
            }
            if (in_array($property, ['color', 'background-color'], true) && !preg_match('/^(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(0|1|0?\.\d+)\s*\)|[a-z]+)$/i', $value)) {
                continue;
            }
            $safe[] = $property . ':' . $value;
        }
        return implode(';', $safe);
    }

    protected static function looksLikeHtml($value)
    {
        $value = (string) $value;
        return (bool) preg_match('/<\s*\/?\s*(p|br|div|span|strong|b|em|i|u|s|ul|ol|li|blockquote|h[1-6]|table|thead|tbody|tfoot|tr|th|td|small|sup|sub|a|pre|code)\b/i', $value);
    }

    protected static function decodeHtmlEntities($value)
    {
        $value = (string) $value;
        for ($i = 0; $i < 2; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }
        return $value;
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
