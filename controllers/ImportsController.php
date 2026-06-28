<?php

class ImportsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('imports/index', [
            'title' => 'ورود اطلاعات از اکسل',
            'batches' => Model::fetchAll('SELECT * FROM import_batches ORDER BY id DESC LIMIT 20'),
        ]);
    }

    public function upload()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
            set_flash('error', 'فایل اکسل انتخاب نشده است.');
            redirect('imports');
        }
        if ($_FILES['excel_file']['size'] > 5 * 1024 * 1024) {
            set_flash('error', 'حجم فایل بیش از حد مجاز است.');
            redirect('imports');
        }
        $name = $_FILES['excel_file']['name'];
        try {
            $rows = SpreadsheetHelper::read($_FILES['excel_file']['tmp_name'], $name);
            $batchId = ImportBatch::create(Auth::id(), $name, $rows);
            $settings = Settings::allKeyed();
            $client = new OpenRouterClient($settings['openrouter_api_key'], $settings['openrouter_model']);
            $prompt = 'این ردیف‌های خام اکسل را به JSON معتبر با کلیدهای customers، contracts، installments و guarantors تبدیل کن. فقط JSON خروجی بده. تاریخ‌ها را اگر شمسی هستند به قالب yyyy/mm/dd شمسی نگه دار. مبلغ‌ها تومان هستند.';
            $result = $client->analyze(json_encode($rows, JSON_UNESCAPED_UNICODE), $prompt);
            if (!$result['ok']) {
                set_flash('error', $result['error']);
                redirect('imports/preview/' . $batchId);
            }
            ImportBatch::saveParsed($batchId, $result['content']);
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
        $errors = $this->saveParsed($parsed);
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

    protected function saveParsed(array $parsed)
    {
        $errors = [];
        $customerByNationalId = [];
        foreach (($parsed['customers'] ?? []) as $index => $customer) {
            $nationalId = to_english_digits($customer['national_id'] ?? '');
            if ($nationalId === '' || empty($customer['full_name']) || empty($customer['mobile'])) {
                $errors[] = 'ردیف مشتری شماره ' . to_persian_digits($index + 1) . ' کامل نیست.';
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
            $nationalId = to_english_digits($contract['customer_national_id'] ?? '');
            $customerId = $customerByNationalId[$nationalId] ?? (Model::fetch("SELECT id FROM users WHERE role = 'customer' AND national_id = ?", [$nationalId])['id'] ?? null);
            $start = parse_jalali_date($contract['start_date'] ?? '');
            $firstDue = parse_jalali_date($contract['first_due_date'] ?? '');
            if (!$customerId || !$start || !$firstDue) {
                $errors[] = 'قرارداد شماره ' . to_persian_digits($index + 1) . ' قابل ذخیره نیست.';
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
                'monthly_interest_rate' => $contract['monthly_interest_rate'] ?? 0,
                'interest_type' => ($contract['interest_type'] ?? 'simple') === 'compound' ? 'compound' : 'simple',
                'months' => $contract['months'] ?? 1,
                'start_date' => $start,
                'first_due_date' => $firstDue,
                'assigned_operator_id' => null,
                'notes' => $contract['notes'] ?? '',
            ], $guarantors);
        }
        return $errors;
    }
}
