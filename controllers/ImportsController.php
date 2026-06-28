<?php

class ImportsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('imports/index', [
            'title' => 'ورود دیتا',
            'batches' => Model::fetchAll('SELECT * FROM import_batches ORDER BY id DESC LIMIT 20'),
        ]);
    }

    public function upload()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            [$name, $rows] = $this->readImportInput();
            $batchId = ImportBatch::create(Auth::id(), $name, $rows);
            $settings = Settings::allKeyed();
            $client = new OpenRouterClient($settings['openrouter_api_key'], $settings['openrouter_model']);
            $prompt = 'این داده خام فارسی یا جدولی را به JSON معتبر با کلیدهای customers، contracts، installments، guarantors و payments تبدیل کن. فقط JSON خروجی بده. اعداد فارسی و انگلیسی را تشخیص بده. تاریخ‌ها را اگر شمسی هستند به قالب yyyy/mm/dd شمسی نگه دار. مبلغ‌ها تومان هستند. نام، کد ملی، موبایل، شماره قرارداد، مبلغ، تاریخ، تعداد اقساط، ضامن، پرداخت و یادداشت را تا حد ممکن جدا و نرمال کن. اگر ستونی با مفهوم پیش پرداخت، بیعانه، پرداخت اولیه، مبلغ اولیه یا down payment وجود داشت آن را با کلید down_payment_amount داخل هر قرارداد برگردان. برای ردیف‌های ناقص، مقدار errors فارسی بگذار و چیزی را حدس خطرناک نزن.';
            $result = $client->analyze(json_encode($rows, JSON_UNESCAPED_UNICODE), $prompt);
            if (!$result['ok']) {
                set_flash('error', $result['error']);
                redirect('imports/preview/' . $batchId);
            }
            $parsed = $this->extractJson($result['content']);
            if ($parsed) {
                $validation = $this->validateParsed($parsed);
                $parsed['_validation_errors'] = $validation;
                ImportBatch::saveParsed($batchId, json_encode($parsed, JSON_UNESCAPED_UNICODE));
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
        } elseif (preg_match('/(\{.*\})/su', $content, $m)) {
            $content = $m[1];
        }
        $json = json_decode($content, true);
        return is_array($json) ? $json : null;
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
            if (empty($customer['full_name']) || empty($customer['mobile']) || empty($customer['national_id'])) {
                $errors[] = 'مشتری ردیف ' . to_persian_digits($index + 1) . ' نام، موبایل یا کد ملی کامل ندارد.';
            }
        }
        foreach (($parsed['contracts'] ?? []) as $index => $contract) {
            $contract = $this->normalizeContractPayload($contract);
            if (empty($contract['customer_national_id']) || normalize_money($contract['principal_amount'] ?? 0) <= 0 || empty($contract['months']) || !parse_jalali_date($contract['start_date'] ?? '') || !parse_jalali_date($contract['first_due_date'] ?? '')) {
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
        foreach (($parsed['customers'] ?? []) as $index => $customer) {
            $nationalId = to_english_digits($customer['national_id'] ?? '');
            if ($nationalId === '' || empty($customer['full_name']) || empty($customer['mobile'])) {
                $errors[] = 'ردیف مشتری شماره ' . to_persian_digits($index + 1) . ' کامل نیست.';
                if ($skipInvalid) {
                    continue;
                }
                continue;
            }
            $existing = Model::fetch("SELECT * FROM users WHERE role = 'customer' AND national_id = ?", [$nationalId]);
            $customerByNationalId[$nationalId] = $existing ? (int) $existing['id'] : User::create([
                'role' => 'customer',
                'username' => null,
                'full_name' => $customer['full_name'],
                'national_id' => $nationalId,
                'mobile' => $customer['mobile'],
                'secondary_phone' => $customer['secondary_phone'] ?? '',
                'email' => '',
                'password' => bin2hex(random_bytes(8)),
                'status' => $customer['status'] ?? 'active',
            ]);
        }
        foreach (($parsed['contracts'] ?? []) as $index => $contract) {
            $contract = $this->normalizeContractPayload($contract);
            $nationalId = to_english_digits($contract['customer_national_id'] ?? '');
            $customerId = $customerByNationalId[$nationalId] ?? (Model::fetch("SELECT id FROM users WHERE role = 'customer' AND national_id = ?", [$nationalId])['id'] ?? null);
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
