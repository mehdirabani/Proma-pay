<?php

class AiController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('ai/index', [
            'title' => 'دستیار هوشمند سامانه',
            'analysis' => null,
            'logs' => AiActionLog::latest(),
        ]);
    }

    public function analyze()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $instruction = trim((string) ($_POST['text'] ?? ''));
        if ($instruction === '') {
            set_flash('error', 'متن دستور را وارد کنید.');
            redirect('ai');
        }
        $settings = Settings::allKeyed();
        $client = new OpenRouterClient($settings['openrouter_api_key'], $settings['openrouter_model']);
        $context = $this->buildSystemContext($instruction);
        $systemPrompt = $this->assistantPrompt();
        $result = $client->analyze("دستور مدیر:\n{$instruction}\n\nداده‌های مجاز سامانه:\n" . json_encode($context, JSON_UNESCAPED_UNICODE), $systemPrompt);
        $structured = null;
        $logId = null;
        if ($result['ok']) {
            $structured = $this->extractJson($result['content']);
            if (!$structured) {
                $structured = [
                    'summary' => $result['content'],
                    'findings' => [],
                    'data' => [],
                    'proposed_actions' => [],
                    'warnings' => ['پاسخ هوش مصنوعی ساختار JSON کامل نداشت و به صورت متن نمایش داده شد.'],
                ];
            }
            $structured = $this->normalizeAiResponse($structured);
            $logId = AiActionLog::createPreview(Auth::id(), $instruction, $structured);
            $structured['log_id'] = $logId;
        }
        $this->render('ai/index', [
            'title' => 'دستیار هوشمند سامانه',
            'analysis' => $result,
            'structured' => $structured,
            'text' => $instruction,
            'logs' => AiActionLog::latest(),
        ]);
    }

    public function confirm($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $log = AiActionLog::find((int) $id);
        if (!$log || $log['status'] !== 'previewed') {
            set_flash('error', 'پیشنهاد قابل تأیید پیدا نشد.');
            redirect('ai');
        }
        $response = json_decode($log['response_json'] ?? '', true);
        if (!is_array($response)) {
            set_flash('error', 'ساختار پیشنهاد هوش مصنوعی معتبر نیست.');
            redirect('ai');
        }
        $result = $this->applyConfirmedActions($response['proposed_actions'] ?? []);
        AiActionLog::markApplied((int) $id, $result['ok'] ? 'applied' : 'approved', $result['summary']);
        set_flash($result['ok'] ? 'success' : 'error', $result['summary']);
        redirect('ai');
    }

    protected function assistantPrompt()
    {
        return 'تو دستیار داخلی سامانه مدیریت قرارداد و اقساط پرما پرداخت هستی. فقط در محدوده همین سامانه پاسخ بده و از فایل‌ها یا داده‌های بیرونی استفاده نکن. موجودیت‌ها: مشتریان، قراردادها، اقساط، پرداخت‌ها، اقساط معوق، پرونده‌های حقوقی، اپراتورها، وکلا و تنظیمات. خروجی را فقط JSON معتبر فارسی بده با کلیدهای summary، findings، data، proposed_actions و warnings. اگر تغییر دیتابیس لازم است، فقط پیشنهاد بده و never اجرا نکن. هر proposed_actions آرایه‌ای از آبجکت‌ها با کلیدهای type، label، description، requires_confirmation و payload باشد. actionهای مجاز برای اجرا بعد از تأیید فقط create_customer و create_contract هستند؛ بقیه را manual_review بگذار. برای قرارداد، پیش‌پرداخت را با کلید down_payment_amount بده و توضیح بده سود روی مانده قابل تقسیط محاسبه می‌شود.';
    }

    protected function buildSystemContext($instruction)
    {
        $needle = '%' . to_english_digits($instruction) . '%';
        return [
            'today_jalali' => jdate(date('Y-m-d')),
            'summary' => [
                'customers' => Model::fetch("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")['total'] ?? 0,
                'active_contracts' => Model::fetch("SELECT COUNT(*) AS total FROM contracts WHERE status = 'active'")['total'] ?? 0,
                'overdue_installments' => Model::fetch("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date < CURDATE()")['total'] ?? 0,
                'open_legal_cases' => Model::fetch("SELECT COUNT(*) AS total FROM legal_cases WHERE status != 'closed'")['total'] ?? 0,
            ],
            'matching_customers' => Model::fetchAll(
                "SELECT id, full_name, national_id, mobile FROM users
                 WHERE role = 'customer' AND (full_name LIKE ? OR national_id LIKE ? OR mobile LIKE ?)
                 ORDER BY id DESC LIMIT 10",
                [$needle, $needle, $needle]
            ),
            'overdue_sample' => Model::fetchAll(
                "SELECT u.full_name, u.mobile, c.contract_number, i.installment_number, i.due_date,
                        GREATEST(i.base_amount - i.paid_amount, 0) AS remaining_amount
                 FROM installments i
                 JOIN contracts c ON c.id = i.contract_id
                 JOIN users u ON u.id = c.customer_id
                 WHERE i.status != 'paid' AND i.due_date < CURDATE()
                 ORDER BY i.due_date ASC LIMIT 15"
            ),
            'legal_sample' => Model::fetchAll(
                "SELECT lc.status, lc.stage, lc.complaint_number, u.full_name, c.contract_number
                 FROM legal_cases lc
                 JOIN users u ON u.id = lc.customer_id
                 JOIN contracts c ON c.id = lc.contract_id
                 ORDER BY lc.id DESC LIMIT 10"
            ),
        ];
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

    protected function normalizeAiResponse(array $response)
    {
        return [
            'summary' => (string) ($response['summary'] ?? 'تحلیل انجام شد.'),
            'findings' => is_array($response['findings'] ?? null) ? $response['findings'] : [],
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
            'proposed_actions' => is_array($response['proposed_actions'] ?? null) ? $response['proposed_actions'] : [],
            'warnings' => is_array($response['warnings'] ?? null) ? $response['warnings'] : [],
        ];
    }

    protected function applyConfirmedActions(array $actions)
    {
        $applied = [];
        $skipped = [];
        foreach ($actions as $action) {
            $type = $action['type'] ?? 'manual_review';
            $payload = is_array($action['payload'] ?? null) ? $action['payload'] : [];
            try {
                if ($type === 'create_customer') {
                    if (empty($payload['full_name']) || empty($payload['mobile'])) {
                        $skipped[] = 'ایجاد مشتری به دلیل کمبود نام یا موبایل انجام نشد.';
                        continue;
                    }
                    $id = User::create([
                        'role' => 'customer',
                        'username' => null,
                        'full_name' => $payload['full_name'],
                        'national_id' => $payload['national_id'] ?? '',
                        'mobile' => $payload['mobile'],
                        'secondary_phone' => $payload['secondary_phone'] ?? '',
                        'email' => '',
                        'password' => bin2hex(random_bytes(8)),
                        'status' => 'active',
                    ]);
                    $applied[] = 'مشتری شماره ' . to_persian_digits($id) . ' ساخته شد.';
                    continue;
                }
                if ($type === 'create_contract') {
                    $customer = null;
                    if (!empty($payload['customer_national_id'])) {
                        $customer = Model::fetch("SELECT id FROM users WHERE role = 'customer' AND national_id = ?", [to_english_digits($payload['customer_national_id'])]);
                    }
                    if (!$customer) {
                        $skipped[] = 'قرارداد پیشنهادی به دلیل نبود مشتری معتبر ساخته نشد.';
                        continue;
                    }
                    $start = parse_jalali_date($payload['start_date'] ?? '') ?: date('Y-m-d');
                    $firstDue = parse_jalali_date($payload['first_due_date'] ?? '') ?: FinanceHelper::addMonths($start, 1);
                    $id = Contract::createWithInstallments([
                        'customer_id' => (int) $customer['id'],
                        'prefix' => $payload['prefix'] ?? '',
                        'principal_amount' => $payload['principal_amount'] ?? 0,
                        'down_payment_amount' => $payload['down_payment_amount'] ?? 0,
                        'monthly_interest_rate' => $payload['monthly_interest_rate'] ?? 0,
                        'interest_type' => ($payload['interest_type'] ?? 'simple') === 'compound' ? 'compound' : 'simple',
                        'months' => $payload['months'] ?? 1,
                        'start_date' => $start,
                        'first_due_date' => $firstDue,
                        'assigned_operator_id' => null,
                        'notes' => $payload['notes'] ?? 'ثبت‌شده پس از تأیید پیشنهاد هوش مصنوعی',
                        'created_by' => Auth::id(),
                    ], []);
                    $applied[] = 'قرارداد شماره ' . to_persian_digits($id) . ' ساخته شد.';
                    continue;
                }
                $skipped[] = 'اقدام «' . ($action['label'] ?? 'بررسی دستی') . '» فقط برای بررسی دستی ثبت شد.';
            } catch (Throwable $e) {
                $skipped[] = $e->getMessage();
            }
        }
        $summary = trim(implode(' ', array_merge($applied, $skipped)));
        return ['ok' => count($applied) > 0 || count($skipped) === 0, 'summary' => $summary ?: 'پیشنهادی برای اعمال وجود نداشت.'];
    }
}
