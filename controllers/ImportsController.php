<?php

class ImportsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        redirect('settings', ['tab' => 'imports']);
    }

    public function upload()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            [$name, $rows] = $this->readImportInput();
            $batchId = ImportBatch::create(Auth::id(), $name, $rows);
            $deterministic = $this->parseRowsWithoutAi($rows);
            if ($this->hasParsedData($deterministic)) {
                $validation = $this->validateParsed($deterministic);
                $deterministic['_validation_errors'] = $validation;
                ImportBatch::saveParsed($batchId, json_encode($deterministic, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                ImportBatch::saveValidation($batchId, $validation);
                redirect('imports/preview/' . $batchId);
            }
            $settings = Settings::allKeyed();
            if (trim((string) ($settings['openrouter_api_key'] ?? '')) === '') {
                $deterministic['_validation_errors'] = ['ساختار داده با parser داخلی شناسایی نشد و کلید هوش مصنوعی تنظیم نیست. ستون‌های فایل را با عنوان‌های فارسی/انگلیسی شناخته‌شده وارد کنید.'];
                ImportBatch::saveParsed($batchId, json_encode($deterministic, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                ImportBatch::saveValidation($batchId, $deterministic['_validation_errors']);
                redirect('imports/preview/' . $batchId);
            }
            $client = new OpenRouterClient($settings['openrouter_api_key'], $settings['openrouter_model']);
            $prompt = 'این داده خام فارسی یا جدولی را به JSON معتبر با کلیدهای customers، contracts، installments، guarantors و payments تبدیل کن. فقط JSON خروجی بده. اعداد فارسی و انگلیسی را تشخیص بده. تاریخ‌ها را اگر شمسی هستند به قالب yyyy/mm/dd شمسی نگه دار. مبلغ‌ها تومان هستند. نام، کد ملی، موبایل، شماره قرارداد، مبلغ، تاریخ، تعداد اقساط، ضامن، پرداخت و یادداشت را تا حد ممکن جدا و نرمال کن. اگر ستونی با مفهوم پیش پرداخت، بیعانه، پرداخت اولیه، مبلغ اولیه یا down payment وجود داشت آن را با کلید down_payment_amount داخل هر قرارداد برگردان. برای ردیف‌های ناقص، مقدار errors فارسی بگذار و چیزی را حدس خطرناک نزن.';
            $result = $client->analyze(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), $prompt);
            if (!$result['ok']) {
                set_flash('error', $result['error']);
                redirect('imports/preview/' . $batchId);
            }
            $parsed = $this->extractJson($result['content']);
            if ($parsed) {
                $validation = $this->validateParsed($parsed);
                $parsed['_validation_errors'] = $validation;
                ImportBatch::saveParsed($batchId, json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                ImportBatch::saveValidation($batchId, $validation);
            } else {
                ImportBatch::saveParsed($batchId, $result['content']);
            }
            redirect('imports/preview/' . $batchId);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('imports');
        }
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $batch = ImportBatch::find((int) $id);
        if (!$batch) {
            set_flash('error', 'بسته ورود دیتا پیدا نشد.');
            redirect('imports');
        }
        if (!ConfirmationCode::verify('import_batch_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تایید حذف بسته ورود دیتا درست وارد نشده است.');
            redirect('settings', ['tab' => 'imports']);
        }
        ImportBatch::delete((int) $id);
        set_flash('success', 'بسته ورود دیتا حذف شد.');
        redirect('settings', ['tab' => 'imports']);
    }

    public function preview($id)
    {
        $this->requireRole('admin');
        $batch = ImportBatch::find((int) $id);
        if (!$batch) {
            set_flash('error', 'بسته واردسازی پیدا نشد.');
            redirect('imports');
        }
        $this->render('imports/preview', [
            'title' => 'پیش‌نمایش واردسازی',
            'batch' => $batch,
            'rows' => ImportBatch::rows((int) $id),
            'parsed' => $this->extractJson($batch['parsed_json'] ?? ''),
        ]);
    }

    public function confirm($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $batch = ImportBatch::find((int) $id);
        $parsed = $this->extractJson($batch['parsed_json'] ?? '');
        if (!$parsed) {
            set_flash('error', 'داده‌های پردازش شده معتبر نیست.');
            redirect('imports/preview/' . (int) $id);
        }
        $validation = $this->validateParsed($parsed);
        $skipInvalid = !empty($_POST['skip_invalid']);
        if ($validation && !$skipInvalid) {
            ImportBatch::saveValidation((int) $id, $validation);
            set_flash('error', 'برخی ردیف‌ها خطا دارند. برای ذخیره فقط ردیف‌های معتبر، گزینه عبور از ردیف‌های نامعتبر را فعال کنید.');
            redirect('imports/preview/' . (int) $id);
        }
        $errors = $this->saveParsed($parsed, $skipInvalid);
        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('imports/preview/' . (int) $id);
        }
        ImportBatch::confirm((int) $id);
        set_flash('success', 'اطلاعات تأیید و ذخیره شد.');
        redirect('imports');
    }

    protected function extractJson($content)
    {
        $content = trim((string) $content);
        if (preg_match('/```json\s*(.*?)```/su', $content, $m)) {
            $content = trim($m[1]);
        } elseif (preg_match('/(\{.*\}|\[.*\])/su', $content, $m)) {
            $content = $m[1];
        }
        $json = json_decode($content, true);
        if (!is_array($json)) {
            return null;
        }
        return $this->normalizeParsedStructure($json);
    }

    protected function normalizeParsedStructure(array $json)
    {
        if (isset($json['data']) && is_array($json['data'])) {
            $json = $json['data'];
        }
        if (isset($json['items']) && is_array($json['items']) && empty($json['customers']) && empty($json['contracts'])) {
            $json = ['rows' => $json['items']];
        } elseif (array_is_list($json)) {
            $json = ['rows' => $json];
        }
        if (!empty($json['rows']) && is_array($json['rows'])) {
            $mapped = $this->normalizeRowsPayload($json['rows']);
            foreach (['customers', 'contracts', 'installments', 'guarantors', 'payments'] as $key) {
                if (!empty($mapped[$key]) && empty($json[$key])) {
                    $json[$key] = $mapped[$key];
                }
            }
        }
        foreach (['customers', 'contracts', 'installments', 'guarantors', 'payments'] as $key) {
            if (empty($json[$key]) || !is_array($json[$key])) {
                $json[$key] = [];
            }
        }
        return $json;
    }

    protected function parseRowsWithoutAi(array $rows)
    {
        $parsed = $this->normalizeParsedStructure(['rows' => $rows]);
        $parsed['_parser'] = 'internal';
        return $parsed;
    }

    protected function hasParsedData(array $parsed)
    {
        foreach (['customers', 'contracts', 'installments', 'guarantors', 'payments'] as $key) {
            if (!empty($parsed[$key]) && is_array($parsed[$key])) {
                return true;
            }
        }
        return false;
    }

    protected function normalizeRowsPayload(array $rows)
    {
        if (!$rows) {
            return [];
        }
        $rows = $this->splitTabPackedRows($rows);
        $records = [];
        if (array_is_list($rows) && isset($rows[0]) && is_array($rows[0]) && array_is_list($rows[0])) {
            if (count($rows[0]) === 1 && !$this->looksLikeHeaderRow($rows[0])) {
                foreach ($rows as $row) {
                    $description = trim((string) ($row[0] ?? ''));
                    if ($description !== '') {
                        $records[] = ['description' => $description];
                    }
                }
            } else {
                $headers = array_map(function ($header) {
                    return $this->normalizeImportKey($header);
                }, $rows[0]);
                foreach (array_slice($rows, 1) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $record = [];
                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $record[$header] = $row[$index] ?? '';
                        }
                    }
                    if ($record) {
                        $records[] = $record;
                    }
                }
            }
        } else {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $record = [];
                foreach ($row as $key => $value) {
                    $record[$this->normalizeImportKey($key)] = $value;
                }
                $records[] = $record;
            }
        }
        $customers = [];
        $contracts = [];
        $payments = [];
        foreach ($records as $record) {
            $inferred = $this->inferImportRecord($record);
            if ($inferred) {
                $customers[] = $inferred['customer'];
                $contracts[] = $inferred['contract'];
                if (!empty($inferred['payment'])) {
                    $payments[] = $inferred['payment'];
                }
                continue;
            }
            $customer = [
                'full_name' => $record['full_name'] ?? $record['name'] ?? '',
                'mobile' => $record['mobile'] ?? $record['phone'] ?? '',
                'secondary_phone' => $record['secondary_phone'] ?? '',
                'national_id' => $record['national_id'] ?? '',
                'status' => $record['status'] ?? 'active',
            ];
            if ($customer['full_name'] || $customer['mobile'] || $customer['national_id']) {
                $customers[] = $customer;
            }
            if (!empty($record['principal_amount']) || !empty($record['amount']) || !empty($record['months'])) {
                $contracts[] = [
                    'customer_national_id' => $record['customer_national_id'] ?? $record['national_id'] ?? '',
                    'principal_amount' => $record['principal_amount'] ?? $record['amount'] ?? '',
                    'down_payment_amount' => $record['down_payment_amount'] ?? '',
                    'monthly_interest_rate' => $record['monthly_interest_rate'] ?? $record['interest_rate'] ?? 0,
                    'months' => $record['months'] ?? '',
                    'start_date' => $record['start_date'] ?? '',
                    'first_due_date' => $record['first_due_date'] ?? '',
                    'notes' => $record['notes'] ?? '',
                ];
            }
            if (!empty($record['payment_amount'])) {
                $payments[] = [
                    'contract_number' => $record['contract_number'] ?? '',
                    'installment_number' => $record['installment_number'] ?? '',
                    'amount' => $record['payment_amount'],
                    'payment_date' => $record['payment_date'] ?? '',
                    'notes' => $record['notes'] ?? '',
                ];
            }
        }
        $customers = $this->uniqueImportCustomers($customers);
        return ['customers' => $customers, 'contracts' => $contracts, 'payments' => $payments];
    }

    protected function looksLikeHeaderRow(array $row)
    {
        $known = [
            'full_name', 'name', 'mobile', 'phone', 'secondary_phone', 'national_id',
            'contract_number', 'amount', 'principal_amount', 'down_payment_amount',
            'monthly_interest_rate', 'interest_rate', 'months', 'start_date',
            'first_due_date', 'payment_amount', 'payment_date', 'installment_number',
            'notes', 'status', 'description', 'reviewdate', 'sharh', 'state',
        ];
        foreach ($row as $cell) {
            if (in_array($this->normalizeImportKey($cell), $known, true)) {
                return true;
            }
        }
        return false;
    }

    protected function splitTabPackedRows(array $rows)
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row) && count($row) === 1) {
                $first = reset($row);
                if (is_string($first) && strpos($first, "\t") !== false) {
                    $normalized[] = explode("\t", $first);
                    continue;
                }
            }
            $normalized[] = $row;
        }
        return $normalized;
    }

    protected function inferImportRecord(array $record)
    {
        $description = trim((string) ($record['description'] ?? $record['sharh'] ?? $record['شرح'] ?? $record['notes'] ?? ''));
        if ($description === '') {
            return null;
        }
        $date = $record['reviewdate'] ?? $record['date'] ?? $record['start_date'] ?? '';
        $phones = $this->extractPhones($description);
        $amount = $this->extractFirstAmount($description);
        $contractNumber = $this->extractContractNumber($description);
        $name = $this->extractCustomerName($description);
        if ($name === '' || !$phones || $amount <= 0) {
            return null;
        }
        $mobile = $phones[0] ?? '';
        $secondary = $phones[1] ?? '';
        $status = $this->normalizeImportedStatus($record['status'] ?? $record['state'] ?? 'active');
        $contract = [
            'customer_national_id' => '',
            'customer_full_name' => $name,
            'customer_mobile' => $mobile,
            'contract_number' => $contractNumber,
            'principal_amount' => $amount,
            'down_payment_amount' => 0,
            'monthly_interest_rate' => 0,
            'months' => 1,
            'start_date' => $date,
            'first_due_date' => $date,
            'notes' => $description,
        ];
        return [
            'customer' => [
                'full_name' => $name,
                'mobile' => $mobile,
                'secondary_phone' => $secondary,
                'national_id' => '',
                'status' => $status,
            ],
            'contract' => $contract,
            'payment' => null,
        ];
    }

    protected function extractPhones($text)
    {
        $text = to_english_digits($text);
        preg_match_all('/(?<!\d)0?9\d{9}(?!\d)/u', $text, $matches);
        $phones = [];
        foreach ($matches[0] ?? [] as $phone) {
            $phone = preg_replace('/\D+/', '', $phone);
            if (strlen($phone) === 10 && strpos($phone, '9') === 0) {
                $phone = '0' . $phone;
            }
            if (strlen($phone) === 11 && !in_array($phone, $phones, true)) {
                $phones[] = $phone;
            }
        }
        return $phones;
    }

    protected function extractFirstAmount($text)
    {
        $text = to_english_digits($text);
        $text = preg_replace('/0?9\d{9}/u', ' ', $text);
        $text = preg_replace('/\bpr\s*\/?\s*\d+\b/iu', ' ', $text);
        if (preg_match('/(?:مبلغ|اصل بدهی)?\s*([0-9]{2,7})(?:\s*(?:تومن|تمن|هزار|میلیون|ملیون))?/u', $text, $match)) {
            return (float) $match[1];
        }
        return 0;
    }

    protected function extractContractNumber($text)
    {
        $text = to_english_digits($text);
        if (preg_match('/\bpr\s*\/?\s*(\d{3,6})\b/iu', $text, $match)) {
            return 'PR/' . $match[1];
        }
        return '';
    }

    protected function extractCustomerName($text)
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $parts = preg_split('/\s+(?:مبلغ|تماس|شماره|اصل|دیرکرد|پرداخت|پیش|[0-9۰-۹])/u', $text, 2);
        $name = trim($parts[0] ?? '');
        return mb_substr(trim($name, " \t\n\r\0\x0B؛;,.،-"), 0, 120, 'UTF-8');
    }

    protected function normalizeImportedStatus($status)
    {
        $status = trim((string) $status);
        if ($status === '' || $status === 'فعال') {
            return 'active';
        }
        if ($status === 'غیرفعال') {
            return 'inactive';
        }
        return $status;
    }

    protected function uniqueImportCustomers(array $customers)
    {
        $seen = [];
        $unique = [];
        foreach ($customers as $customer) {
            $key = to_english_digits($customer['national_id'] ?? '') ?: to_english_digits($customer['mobile'] ?? '') ?: mb_strtolower($customer['full_name'] ?? '', 'UTF-8');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $customer;
        }
        return $unique;
    }

    protected function normalizeImportKey($key)
    {
        $key = trim(mb_strtolower(to_english_digits((string) $key), 'UTF-8'));
        $key = str_replace([' ', '-', '‌'], '_', $key);
        $aliases = [
            'نام' => 'full_name',
            'نام_و_نام_خانوادگی' => 'full_name',
            'full_name' => 'full_name',
            'name' => 'name',
            'reviewdate' => 'reviewdate',
            'sharh' => 'sharh',
            'state' => 'state',
            'customercode' => 'customercode',
            'description' => 'description',
            'date' => 'date',
            'موبایل' => 'mobile',
            'شماره_تماس' => 'mobile',
            'phone' => 'phone',
            'mobile' => 'mobile',
            'تلفن_دوم' => 'secondary_phone',
            'secondary_phone' => 'secondary_phone',
            'کد_ملی' => 'national_id',
            'national_id' => 'national_id',
            'شماره_قرارداد' => 'contract_number',
            'contract_number' => 'contract_number',
            'مبلغ' => 'amount',
            'مبلغ_قرارداد' => 'principal_amount',
            'principal_amount' => 'principal_amount',
            'پیش_پرداخت' => 'down_payment_amount',
            'down_payment' => 'down_payment_amount',
            'down_payment_amount' => 'down_payment_amount',
            'سود' => 'monthly_interest_rate',
            'نرخ_سود' => 'monthly_interest_rate',
            'interest_rate' => 'interest_rate',
            'monthly_interest_rate' => 'monthly_interest_rate',
            'اقساط' => 'months',
            'تعداد_اقساط' => 'months',
            'months' => 'months',
            'تاریخ_شروع' => 'start_date',
            'start_date' => 'start_date',
            'اولین_سررسید' => 'first_due_date',
            'first_due_date' => 'first_due_date',
            'مبلغ_پرداخت' => 'payment_amount',
            'payment_amount' => 'payment_amount',
            'تاریخ_پرداخت' => 'payment_date',
            'payment_date' => 'payment_date',
            'شماره_قسط' => 'installment_number',
            'installment_number' => 'installment_number',
            'یادداشت' => 'notes',
            'notes' => 'notes',
            'وضعیت' => 'status',
            'status' => 'status',
        ];
        return $aliases[$key] ?? $key;
    }

    protected function readImportInput()
    {
        $file = $_FILES['data_file'] ?? $_FILES['excel_file'] ?? null;
        $rawText = trim((string) ($_POST['raw_text'] ?? ''));
        if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            if ((int) $file['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('حجم فایل بیش از حد مجاز است.');
            }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'csv', 'txt'], true)) {
                throw new RuntimeException('پسوند فایل مجاز نیست. فقط اکسل، سی‌اس‌وی یا متن پذیرفته می‌شود.');
            }
            return [$file['name'], SpreadsheetHelper::read($file['tmp_name'], $file['name'])];
        }
        if ($rawText !== '') {
            return ['متن چسبانده‌شده ' . jdate(date('Y-m-d')), SpreadsheetHelper::readRawText($rawText)];
        }
        throw new RuntimeException('فایل یا متن خام برای ورود دیتا انتخاب نشده است.');
    }

    protected function validateParsed(array $parsed)
    {
        $errors = [];
        foreach (($parsed['customers'] ?? []) as $index => $customer) {
            if (empty($customer['full_name']) || (empty($customer['mobile']) && empty($customer['national_id']))) {
                $errors[] = 'مشتری ردیف ' . to_persian_digits($index + 1) . ' نام، موبایل یا کد ملی کامل ندارد.';
            }
        }
        foreach (($parsed['contracts'] ?? []) as $index => $contract) {
            $contract = $this->normalizeContractPayload($contract);
            $hasCustomerKey = !empty($contract['customer_national_id']) || !empty($contract['customer_mobile']);
            if (!$hasCustomerKey || normalize_money($contract['principal_amount'] ?? 0) <= 0 || empty($contract['months']) || !parse_jalali_date($contract['start_date'] ?? '') || !parse_jalali_date($contract['first_due_date'] ?? '')) {
                $errors[] = 'قرارداد ردیف ' . to_persian_digits($index + 1) . ' مشتری، مبلغ یا تعداد اقساط معتبر ندارد.';
            }
            if (normalize_money($contract['down_payment_amount'] ?? 0) > normalize_money($contract['principal_amount'] ?? 0)) {
                $errors[] = 'پیش‌پرداخت قرارداد ردیف ' . to_persian_digits($index + 1) . ' بیشتر از مبلغ اصل است.';
            }
        }
        foreach (($parsed['payments'] ?? []) as $index => $payment) {
            if (empty($payment['contract_number']) || normalize_money($payment['amount'] ?? 0) <= 0) {
                $errors[] = 'پرداخت ردیف ' . to_persian_digits($index + 1) . ' شماره قرارداد یا مبلغ معتبر ندارد.';
            }
        }
        return $errors;
    }

    protected function saveParsed(array $parsed, $skipInvalid = false)
    {
        $errors = [];
        $customerByNationalId = [];
        $customerByMobile = [];
        foreach (($parsed['customers'] ?? []) as $index => $customer) {
            $nationalId = to_english_digits($customer['national_id'] ?? '');
            $mobile = to_english_digits($customer['mobile'] ?? '');
            if (empty($customer['full_name']) || ($nationalId === '' && $mobile === '')) {
                $errors[] = 'ردیف مشتری شماره ' . to_persian_digits($index + 1) . ' کامل نیست.';
                if ($skipInvalid) {
                    continue;
                }
                continue;
            }
            $existing = $nationalId !== ''
                ? Model::fetch("SELECT * FROM users WHERE role = 'customer' AND national_id = ?", [$nationalId])
                : Model::fetch("SELECT * FROM users WHERE role = 'customer' AND mobile = ?", [$mobile]);
            $customerId = $existing ? (int) $existing['id'] : User::create([
                'role' => 'customer',
                'username' => null,
                'full_name' => $customer['full_name'],
                'national_id' => $nationalId,
                'mobile' => $mobile,
                'secondary_phone' => $customer['secondary_phone'] ?? '',
                'email' => '',
                'password' => '',
                'status' => $customer['status'] ?? 'active',
            ]);
            if ($nationalId !== '') {
                $customerByNationalId[$nationalId] = $customerId;
            }
            if ($mobile !== '') {
                $customerByMobile[$mobile] = $customerId;
            }
        }
        foreach (($parsed['contracts'] ?? []) as $index => $contract) {
            $contract = $this->normalizeContractPayload($contract);
            $nationalId = to_english_digits($contract['customer_national_id'] ?? '');
            $mobile = to_english_digits($contract['customer_mobile'] ?? '');
            $customerId = $nationalId !== '' ? ($customerByNationalId[$nationalId] ?? (Model::fetch("SELECT id FROM users WHERE role = 'customer' AND national_id = ?", [$nationalId])['id'] ?? null)) : null;
            if (!$customerId && $mobile !== '') {
                $customerId = $customerByMobile[$mobile] ?? (Model::fetch("SELECT id FROM users WHERE role = 'customer' AND mobile = ?", [$mobile])['id'] ?? null);
            }
            $start = parse_jalali_date($contract['start_date'] ?? '');
            $firstDue = parse_jalali_date($contract['first_due_date'] ?? '');
            $principal = normalize_money($contract['principal_amount'] ?? 0);
            $downPayment = normalize_money($contract['down_payment_amount'] ?? 0);
            $months = (int) to_english_digits($contract['months'] ?? 0);
            if (!$customerId || !$start || !$firstDue || $principal <= 0 || $months <= 0 || $downPayment > $principal) {
                $errors[] = 'قرارداد شماره ' . to_persian_digits($index + 1) . ' قابل ذخیره نیست.';
                if ($skipInvalid) {
                    continue;
                }
                continue;
            }
            $guarantors = [];
            foreach (($contract['guarantor_national_ids'] ?? []) as $gid) {
                $g = Model::fetch("SELECT id FROM users WHERE role = 'customer' AND national_id = ?", [to_english_digits($gid)]);
                if ($g) {
                    $guarantors[] = (int) $g['id'];
                }
            }
            Contract::createWithInstallments([
                'customer_id' => $customerId,
                'prefix' => $contract['prefix'] ?? '',
                'principal_amount' => $contract['principal_amount'] ?? 0,
                'down_payment_amount' => $contract['down_payment_amount'] ?? 0,
                'monthly_interest_rate' => $contract['monthly_interest_rate'] ?? 0,
                'interest_type' => ($contract['interest_type'] ?? 'simple') === 'compound' ? 'compound' : 'simple',
                'months' => $contract['months'] ?? 1,
                'start_date' => $start,
                'first_due_date' => $firstDue,
                'assigned_operator_id' => null,
                'notes' => $contract['notes'] ?? '',
                'created_by' => Auth::id(),
            ], $guarantors);
        }
        foreach (($parsed['payments'] ?? []) as $index => $payment) {
            $contract = Model::fetch('SELECT id FROM contracts WHERE contract_number = ?', [$payment['contract_number'] ?? '']);
            if (!$contract || normalize_money($payment['amount'] ?? 0) <= 0) {
                $errors[] = 'پرداخت شماره ' . to_persian_digits($index + 1) . ' قابل ذخیره نیست.';
                if ($skipInvalid) {
                    continue;
                }
                continue;
            }
            $installmentId = null;
            if (!empty($payment['installment_number'])) {
                $installment = Model::fetch('SELECT id FROM installments WHERE contract_id = ? AND installment_number = ?', [(int) $contract['id'], (int) to_english_digits($payment['installment_number'])]);
                $installmentId = $installment['id'] ?? null;
            }
            if ($installmentId) {
                Payment::record((int) $installmentId, (int) $contract['id'], Auth::id(), $payment['amount'], 'manual', 'paid', null, null, $payment['notes'] ?? 'پرداخت واردشده از ورود دیتا', parse_jalali_date($payment['payment_date'] ?? '') ?: date('Y-m-d'));
            }
        }
        return $skipInvalid ? [] : $errors;
    }

    protected function normalizeContractPayload(array $contract)
    {
        $aliases = [
            'down_payment_amount',
            'down_payment',
            'down payment',
            'پیش پرداخت',
            'پیش‌پرداخت',
            'پیش_پرداخت',
            'بیعانه',
            'پرداخت اولیه',
            'مبلغ اولیه',
            'مبلغ_اولیه',
        ];
        foreach ($aliases as $key) {
            if (isset($contract[$key]) && $contract[$key] !== '') {
                $contract['down_payment_amount'] = $contract[$key];
                break;
            }
        }
        return $contract;
    }
}
