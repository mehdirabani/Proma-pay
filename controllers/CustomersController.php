<?php

class CustomersController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $result = User::customerSummariesPaginated($_GET['q'] ?? null, $_GET['status'] ?? null, $_GET['page'] ?? 1, 24);
        $customers = $result['items'];
        $ids = array_column($customers, 'id');
        $loyalty = CustomerLoyalty::summariesForUsers($ids);
        $creditProfiles = [];
        if (class_exists('CreditRiskEngine')) {
            try { $creditProfiles = CreditRiskEngine::summariesForCustomers($ids, true, 21600); }
            catch (Throwable $e) { if (class_exists('ErrorHandler')) ErrorHandler::log('customer_credit_profiles_list', $e, 500); }
        }
        $timelines = Payment::recentForCustomers($ids, 3);
        $trends = Payment::monthlyTrendsForCustomers($ids);
        $verifiedDocuments = IdentityDocument::verifiedForUsers($ids);
        foreach ($customers as &$customer) {
            $total = max(1, (int) ($customer['installment_count'] ?? 0));
            $paidScore = ((int) ($customer['paid_installments'] ?? 0) / $total) * 100;
            $penalty = min(70, ((int) ($customer['overdue_installments'] ?? 0)) * 9);
            $customer['good_score'] = max(0, min(100, (int) ceil($paidScore - $penalty)));
            $customer['loyalty'] = $loyalty[(int) $customer['id']] ?? ['points' => 0, 'tier' => null, 'next_tier' => null, 'progress' => 0, 'medals' => []];
            $customer['credit_profile'] = $creditProfiles[(int) $customer['id']] ?? null;
            $customer['medals'] = $customer['loyalty']['medals'];
            $customer['identity_verified'] = !empty($verifiedDocuments[(int) $customer['id']]);
            $customer['payment_timeline'] = $timelines[(int) $customer['id']] ?? [];
            $customer['payment_trend'] = $trends[(int) $customer['id']] ?? array_fill(0, 6, 0);
        }
        unset($customer);
        $this->render('customers/index', [
            'title' => 'مدیریت مشتریان',
            'customers' => $customers,
            'pagination' => $result,
            'loyaltyRules' => CustomerLoyalty::rules('manual', true),
        ], is_ajax_request() ? null : 'app');
    }

    public function export()
    {
        $this->requireRole('admin');
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = in_array($_GET['status'] ?? '', ['active', 'inactive', 'blocked'], true) ? $_GET['status'] : null;
        SpreadsheetHelper::downloadPaginatedXlsx(
            'مشتریان.xlsx',
            'مشتریان',
            ['نام مشتری', 'کد ملی', 'موبایل', 'تلفن دوم', 'وضعیت', 'تعداد قرارداد', 'اقساط معوق', 'مبلغ پرداخت‌شده (تومان)', 'تاریخ ثبت'],
            static fn (int $page, int $perPage): array => User::customerSummariesPaginated($search, $status, $page, $perPage),
            static fn (array $row): array => [
                (string) ($row['full_name'] ?? ''), (string) ($row['national_id'] ?? ''),
                (string) ($row['mobile'] ?? ''), (string) ($row['secondary_phone'] ?? ''),
                status_label($row['status'] ?? ''), (int) ($row['contract_count'] ?? 0),
                (int) ($row['overdue_installments'] ?? 0), normalize_money($row['paid_total'] ?? 0),
                (string) ($row['created_at'] ?? ''),
            ]
        );
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $wantsJson = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $validator = (new Validator($_POST))
            ->required('full_name', 'نام کامل')
            ->required('national_id', 'کد ملی')
            ->required('mobile', 'موبایل')
            ->nationalId('national_id', 'کد ملی')
            ->mobile('mobile', 'موبایل');
        if (!$validator->passes()) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'message' => implode(' ', $validator->errors())], 422);
            }
            set_flash('error', implode(' ', $validator->errors()));
            redirect('customers');
        }
        try {
            $customerId = User::create([
                'role' => 'customer',
                'username' => null,
                'full_name' => $_POST['full_name'] ?? '',
                'father_name' => $_POST['father_name'] ?? '',
                'issued_from' => $_POST['issued_from'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => $_POST['secondary_phone'] ?? '',
                'email' => '',
                'password' => '',
                'status' => $_POST['status'] ?? 'active',
                'address' => $_POST['address'] ?? '',
            ]);
            if ($wantsJson) {
                $customer = User::find((int) $customerId);
                $this->json(['ok' => true, 'customer' => [
                    'id' => (int) $customerId,
                    'full_name' => (string) ($customer['full_name'] ?? $_POST['full_name'] ?? ''),
                    'mobile' => (string) ($customer['mobile'] ?? $_POST['mobile'] ?? ''),
                    'national_id' => (string) ($customer['national_id'] ?? $_POST['national_id'] ?? ''),
                    'status' => (string) ($customer['status'] ?? 'active'),
                ]]);
            }
            set_flash('success', 'مشتری با موفقیت ثبت شد.');
        } catch (Throwable $e) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت مشتری انجام نشد. کد ملی یا موبایل را بررسی کنید.'], 422);
            }
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت مشتری انجام نشد. کد ملی یا موبایل را بررسی کنید.');
        }
        redirect('customers');
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $validator = (new Validator($_POST))->required('full_name', 'نام کامل')->nationalId('national_id', 'کد ملی')->mobile('mobile', 'موبایل');
        if (!$validator->passes()) {
            set_flash('error', implode(' ', $validator->errors()));
            redirect('customers');
        }
        try {
            User::updateUser((int) $id, [
                'role' => 'customer',
                'full_name' => $_POST['full_name'] ?? '',
                'father_name' => $_POST['father_name'] ?? '',
                'issued_from' => $_POST['issued_from'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => $_POST['secondary_phone'] ?? '',
                'email' => '',
                'status' => $_POST['status'] ?? 'active',
                'address' => $_POST['address'] ?? '',
            ]);
            set_flash('success', 'اطلاعات مشتری به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش مشتری انجام نشد.');
        }
        redirect('customers');
    }

    public function merge()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            User::mergeCustomers($_POST['keep_customer_id'] ?? 0, $_POST['merge_customer_id'] ?? 0, Auth::id());
            set_flash('success', 'ادغام مشتریان با حفظ اطلاعات انجام شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ادغام مشتریان انجام نشد.');
        }
        redirect('customers');
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (!ConfirmationCode::verify('customer_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تأیید حذف مشتری درست وارد نشده است.');
            redirect('customers');
        }
        $activeContracts = Model::fetch("SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ? AND status = 'active'", [(int) $id]);
        if ((int) ($activeContracts['total'] ?? 0) > 0) {
            set_flash('error', 'این مشتری دارای قرارداد فعال است و امکان حذف او وجود ندارد.');
            redirect('customers');
        }
        User::deleteUser((int) $id);
        set_flash('success', 'مشتری حذف شد.');
        redirect('customers');
    }

    public function show($id)
    {
        $this->requireRole('admin');
        $customer = User::find((int) $id);
        if (!$customer || $customer['role'] !== 'customer') {
            set_flash('error', 'مشتری پیدا نشد.');
            redirect('customers');
        }

        $accountingAvailable = class_exists('PluginManager') && PluginManager::isActive('proma-accounting');
        $account = null;
        if ($accountingAvailable && class_exists('Proma\\Plugins\\Accounting\\Services\\AccountBalanceService')) {
            try {
                $account = Proma\Plugins\Accounting\Services\AccountBalanceService::snapshot((int) $id);
            } catch (Throwable $ignored) {
                $accountingAvailable = false;
            }
        }
        $creditProfile = null;
        if (class_exists('CreditRiskEngine')) {
            try { $creditProfile = CreditRiskEngine::summaryForCustomer((int) $id, 21600); }
            catch (Throwable $ignored) { $creditProfile = null; }
        }
        $this->render('customers/show', [
            'title' => 'نمای ۳۶۰ مشتری',
            'customer' => $customer,
            'summary' => Customer360Service::summary((int) $id),
            'account' => $account,
            'accountingAvailable' => $accountingAvailable,
            'medals' => CustomerLoyalty::medalsForUsers([(int) $id])[(int) $id] ?? [],
            'loyaltyProfile' => CustomerLoyalty::profile((int) $id),
            'creditProfile' => $creditProfile,
        ]);
    }

    /** First-open tab loader for the bounded Customer 360 workspace. */
    public function tab($id, $tab)
    {
        $this->requireRole('admin');
        $customerId = (int) $id;
        $customer = User::find($customerId);
        $allowed = ['overview', 'contracts', 'installments', 'payments', 'documents', 'accounting', 'calls', 'referrals', 'legal', 'timeline', 'loyalty'];
        if (!$customer || ($customer['role'] ?? '') !== 'customer' || !in_array((string) $tab, $allowed, true)) {
            http_response_code(404);
            echo 'بخش موردنظر پیدا نشد.';
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = ['customer' => $customer, 'tab' => (string) $tab, 'pageUrl' => static function ($nextPage) use ($customerId, $tab) {
            return url('customers/tab/' . $customerId . '/' . $tab, ['page' => max(1, (int) $nextPage)]);
        }];

        switch ($tab) {
            case 'overview':
                $data['contracts'] = Contract::paginated(['customer_id' => $customerId, 'page' => 1, 'per_page' => 5]);
                $data['payments'] = Payment::recentForCustomer($customerId, 5);
                break;
            case 'contracts':
                $data['result'] = Contract::paginated(['customer_id' => $customerId, 'page' => $page, 'per_page' => 10]);
                break;
            case 'installments':
                $data['result'] = Installment::filtered(['customer_id' => $customerId, 'page' => $page, 'per_page' => 20, 'sort' => 'due_asc']);
                break;
            case 'payments':
                $perPage = 20;
                $filters = ['customer_id' => $customerId, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage];
                $total = Payment::logs($filters + ['count_only' => true]);
                $pages = max(1, (int) ceil($total / $perPage));
                $page = min($page, $pages);
                $filters['offset'] = ($page - 1) * $perPage;
                $data['result'] = ['items' => Payment::logs($filters), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)), 'per_page' => $perPage];
                break;
            case 'documents':
                $data['identityDocuments'] = IdentityDocument::forUser($customerId, 50);
                $data['customerFiles'] = FileRecord::forCustomer($customerId, 50);
                break;
            case 'accounting':
                $data['available'] = class_exists('PluginManager') && PluginManager::isActive('proma-accounting')
                    && class_exists('Proma\\Plugins\\Accounting\\Services\\AccountingRepository');
                if ($data['available']) {
                    $data['account'] = \Proma\Plugins\Accounting\Services\AccountBalanceService::snapshot($customerId);
                    $data['result'] = \Proma\Plugins\Accounting\Services\AccountingRepository::ledger($customerId, $page, 20);
                    $data['accountSummary'] = \Proma\Plugins\Accounting\Services\AccountingRepository::accountSummary($customerId);
                }
                break;
            case 'calls':
                $data['calls'] = OperatorCall::forCustomer($customerId, 50);
                $data['followups'] = Event::customerFollowups($customerId, 30);
                break;
            case 'referrals':
                $data['available'] = class_exists('PluginManager') && PluginManager::isActive('proma-affiliate');
                $data['referralData'] = $data['available'] ? Customer360Service::referrals($customerId) : [];
                break;
            case 'legal':
                $data['result'] = LegalCase::paginated(['customer_id' => $customerId, 'page' => $page, 'per_page' => 15]);
                break;
            case 'timeline':
                $data['timeline'] = Customer360Service::timeline($customerId, 40);
                break;
            case 'loyalty':
                $data['loyaltyProfile'] = CustomerLoyalty::profile($customerId, 50);
                $data['loyaltyRules'] = CustomerLoyalty::rules('manual', true);
                break;
        }

        if (!is_ajax_request()) {
            $accountingAvailable = class_exists('PluginManager') && PluginManager::isActive('proma-accounting');
            $account = null;
            if ($accountingAvailable && class_exists('Proma\\Plugins\\Accounting\\Services\\AccountBalanceService')) {
                try { $account = \Proma\Plugins\Accounting\Services\AccountBalanceService::snapshot($customerId); }
                catch (Throwable $ignored) { $accountingAvailable = false; }
            }
            $this->render('customers/show', [
                'title' => 'نمای ۳۶۰ مشتری', 'customer' => $customer,
                'summary' => Customer360Service::summary($customerId), 'account' => $account,
                'accountingAvailable' => $accountingAvailable,
                'medals' => CustomerLoyalty::medalsForUsers([$customerId])[$customerId] ?? [],
                'activeTab' => (string) $tab, 'activeTabData' => $data,
            ]);
            return;
        }
        $this->render('customers/tabs/' . $tab, $data, null);
    }

    public function identityDocument($id)
    {
        $this->requireRole('admin');
        $document = IdentityDocument::find((int) $id);
        $customer = $document ? User::find((int) ($document['user_id'] ?? 0)) : null;
        if (!$document || !$customer || ($customer['role'] ?? '') !== 'customer' || trim((string) ($document['file_path'] ?? '')) === '') {
            http_response_code(404);
            echo 'مدرک پیدا نشد.';
            return;
        }

        $path = UploadHelper::absolutePath((string) $document['file_path']);
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo 'محتوای مدرک در فضای امن پیدا نشد.';
            return;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeByExtension = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        $mime = $mimeByExtension[$extension] ?? 'application/octet-stream';
        $download = !empty($_GET['download']);
        $inline = !$download && in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
        $name = IdentityDocument::typeLabel((string) ($document['document_type'] ?? '')) . '.' . ($extension ?: 'bin');
        $name = trim(preg_replace('/[\r\n"]+/', '', $name));

        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="document"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public function medalStore($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        set_flash('info', 'برای ثبت فعالیت امتیازی، صفحه امتیاز و سطح‌بندی مشتری را باز کنید.');
        redirect('customers/tab/' . (int) $id . '/loyalty');
    }

    public function awardPoints($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            CustomerLoyalty::awardManualActivity((int) $id, (int) ($_POST['rule_id'] ?? 0), $_POST['note'] ?? '', Auth::id());
            set_flash('success', 'امتیاز فعالیت برای مشتری ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت امتیاز فعالیت انجام نشد.');
        }
        redirect('customers/tab/' . (int) $id . '/loyalty');
    }

    public function medalUpdate($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        set_flash('info', 'مدال‌های سطحی بر پایه امتیاز مشتری نگهداری می‌شوند و از این مسیر ویرایش نمی‌شوند.');
        redirect('loyalty');
    }

    public function medalDelete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        set_flash('info', 'سابقه مدال سطحی مشتری حفظ می‌شود. برای مدیریت سطح‌ها به باشگاه امتیاز مشتریان بروید.');
        redirect('loyalty');
    }
}
