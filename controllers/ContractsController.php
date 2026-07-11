<?php

class ContractsController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $readOnly = Auth::role() === 'operator';
        $today = date('Y-m-d');
        $result = Contract::paginated([
            'search' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'per_page' => 24,
        ]);
        $this->render('contracts/index', [
            'title' => $readOnly ? 'قراردادها' : 'مدیریت قراردادها',
            'contracts' => $result['items'],
            'pagination' => $result,
            'customers' => [],
            'operators' => $readOnly ? [] : User::all('operator'),
            'settings' => Settings::allKeyed(),
            'defaultStartDate' => jdate($today),
            'defaultFirstDueDate' => jdate(FinanceHelper::addMonths($today, 1)),
            'readOnly' => $readOnly,
            'contractsRoute' => 'contracts',
            'readOnlyTitle' => 'فهرست قراردادها',
        ], is_ajax_request() ? null : 'app');
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $reusedCustomer = null;
        try {
            $customerId = $this->resolveCustomer($reusedCustomer);
            $startDate = parse_jalali_date($_POST['start_date'] ?? '') ?: date('Y-m-d');
            $firstDue = parse_jalali_date($_POST['first_due_date'] ?? '') ?: FinanceHelper::addMonths($startDate, 1);
            if (!$customerId || !$startDate || !$firstDue) {
                throw new InvalidArgumentException('اطلاعات مشتری و تاریخ‌های قرارداد باید کامل و معتبر باشد.');
            }
            Contract::createWithInstallments([
                'customer_id' => $customerId,
                'prefix' => $_POST['prefix'] ?? '',
                'principal_amount' => $_POST['principal_amount'] ?? 0,
                'down_payment_amount' => $_POST['down_payment_amount'] ?? 0,
                'monthly_interest_rate' => $_POST['monthly_interest_rate'] ?? 0,
                'interest_type' => ($_POST['interest_type'] ?? 'compound') === 'simple' ? 'simple' : 'compound',
                'months' => $_POST['months'] ?? 6,
                'start_date' => $startDate,
                'first_due_date' => $firstDue,
                'assigned_operator_id' => $_POST['assigned_operator_id'] ?? null,
                'notes' => $_POST['notes'] ?? '',
                'created_by' => Auth::id(),
            ], $_POST['guarantors'] ?? [], $_POST['items'] ?? [], $_POST['guarantee'] ?? [], $_POST['guarantor_people'] ?? []);
            $message = 'قرارداد و اقساط آن با موفقیت ساخته شد.';
            if ($reusedCustomer) {
                $message .= ' مشتری «' . $reusedCustomer . '» از قبل وجود داشت و همان پرونده برای قرارداد انتخاب شد.';
            }
            set_flash('success', $message);
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت قرارداد انجام نشد. شماره قرارداد یا داده‌های ورودی را بررسی کنید.');
        }
        redirect('contracts');
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $id);
        if (!$contract) {
            set_flash('error', 'قرارداد پیدا نشد.');
            redirect('contracts');
        }
        try {
            $payload = $this->buildContractUpdatePayload($contract, $_POST);
            Contract::updateContract((int) $id, $payload, $_POST['guarantors'] ?? [], $_POST['items'] ?? [], $_POST['guarantee'] ?? [], $_POST['guarantor_people'] ?? []);
            set_flash('success', 'قرارداد به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش قرارداد انجام نشد. داده‌های ورودی را بررسی کنید.');
        }
        redirect('contracts');
    }

    public function bulkUpdate()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $result = Contract::bulkUpdate($_POST, Auth::id());
            set_flash('success', 'ویرایش دسته‌جمعی روی ' . to_persian_digits($result['updated']) . ' قرارداد اعمال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش دسته‌جمعی قراردادها انجام نشد.');
        }
        redirect('contracts', [
            'q' => $_POST['return_q'] ?? null,
            'view' => $_POST['return_view'] ?? null,
            'page' => $_POST['return_page'] ?? null,
        ]);
    }

    public function show($id)
    {
        Auth::requireLogin();
        $contractId = (int) $id;
        LegalCase::ensureSchema();
        LegalCaseLog::ensureSchema();

        $contract = Contract::find($contractId);
        $this->authorizeContractAccess($contract);

        $user = Auth::user();
        $isCustomer = (Auth::role() ?? '') === 'customer';
        $legalCases = $isCustomer ? [] : LegalCase::forContract($contractId);
        $currentLegalCase = $legalCases[0] ?? null;
        $legalLogs = $isCustomer ? [] : LegalCaseLog::forContract($contractId);
        $latestLegalLog = $legalLogs[0] ?? null;
        $legalAttachments = [];
        foreach ($legalLogs as $log) {
            if (!empty($log['attachment_path'])) {
                $legalAttachments[] = $log;
            }
        }

        $canManageLegalLogs = !$isCustomer && $this->canManageLegalAction($user, $contract, $currentLegalCase);
        $canEditLegalCosts = !$isCustomer && $this->canEditLegalCosts($user, $contract, $currentLegalCase);
        $canViewLegalCosts = !$isCustomer && $this->canViewLegalCosts($user, $contract, $currentLegalCase);
        $canReferToLegal = !$isCustomer && $this->canReferToLegal($user, $contract, $currentLegalCase);
        $canViewFinancialSummary = $this->canViewFinancialSummary($user);

        $financialSummary = $canViewFinancialSummary
            ? ContractFinancialSummaryService::summarize($contractId, date('Y-m-d'))
            : null;

        $editableLogIds = [];
        $deletableLogIds = [];
        foreach ($legalLogs as $log) {
            if ($this->canUpdateLegalLog($user, $contract, $log, $currentLegalCase)) {
                $editableLogIds[] = (int) $log['id'];
            }
            if ($this->canDeleteLegalLog($user, $contract, $log, $currentLegalCase)) {
                $deletableLogIds[] = (int) $log['id'];
            }
        }

        $this->render('contracts/show', [
            'title' => 'جزئیات قرارداد',
            'contract' => $contract,
            'document' => ContractDocument::document($contractId),
            'documentTitle' => ContractDocument::renderTitle($contractId),
            'documentHeader' => ContractDocument::renderHeader($contractId),
            'items' => ContractDocument::items($contractId),
            'guarantees' => ContractDocument::guarantees($contractId),
            'guarantorPeople' => ContractDocument::guarantorPeople($contractId),
            'installments' => Installment::all(['contract_id' => $contractId]),
            'paymentTimeline' => Payment::recentForContract($contractId, 8),
            'logs' => ContractDocument::logs($contractId),
            'canManageDocument' => Auth::role() === 'admin',
            'legalCases' => $legalCases,
            'currentLegalCase' => $currentLegalCase,
            'legalLogs' => $legalLogs,
            'latestLegalLog' => $latestLegalLog,
            'legalAttachments' => $legalAttachments,
            'legalStageOptions' => LegalCaseLog::stageOptions(),
            'legalCostTypeOptions' => LegalCaseLog::costTypeOptions(),
            'lawyers' => $isCustomer ? [] : User::all('lawyer'),
            'canManageLegalLogs' => $canManageLegalLogs,
            'canEditLegalCosts' => $canEditLegalCosts,
            'canViewLegalCosts' => $canViewLegalCosts,
            'canReferToLegal' => $canReferToLegal,
            'canViewFinancialSummary' => $canViewFinancialSummary,
            'financialSummary' => $financialSummary,
            'financialSummaryDate' => date('Y-m-d'),
            'legalLogCostTotal' => $canViewLegalCosts ? LegalCaseLog::costTotalForContract($contractId) : 0,
            'legacyLegalCostTotal' => $canViewLegalCosts ? LegalCase::expenseTotalForContract($contractId) : 0,
            'editableLegalLogIds' => $editableLogIds,
            'deletableLegalLogIds' => $deletableLogIds,
            'cancellationSummary' => Contract::cancellationSummary($contractId),
        ]);
    }

    public function storeLegalLog($contractId)
    {
        Auth::requireLogin();
        $this->onlyPost();

        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        $user = Auth::user();
        $currentLegalCase = LegalCase::latestForContract((int) $contractId);

        if (!$this->canManageLegalAction($user, $contract, $currentLegalCase)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }

        try {
            $payload = $this->buildLegalLogPayload((int) $contractId, $currentLegalCase, $this->canEditLegalCosts($user, $contract, $currentLegalCase));
            LegalCaseLog::createLog($payload, $_FILES['attachment'] ?? []);
            set_flash('success', 'مرحله حقوقی با موفقیت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت مرحله حقوقی انجام نشد.');
        }

        redirect('contracts/show/' . (int) $contractId);
    }

    public function updateLegalLog($id)
    {
        Auth::requireLogin();
        $this->onlyPost();

        $log = LegalCaseLog::find((int) $id);
        if (!$log) {
            set_flash('error', 'لاگ حقوقی پیدا نشد.');
            redirect('contracts');
        }

        $contract = Contract::find((int) $log['contract_id']);
        $this->authorizeContractAccess($contract);
        $user = Auth::user();
        $currentLegalCase = LegalCase::latestForContract((int) $log['contract_id']);

        if (!$this->canUpdateLegalLog($user, $contract, $log, $currentLegalCase)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }

        try {
            $payload = $this->buildLegalLogPayload((int) $log['contract_id'], $currentLegalCase, $this->canEditLegalCosts($user, $contract, $currentLegalCase));
            LegalCaseLog::updateLog((int) $id, $payload + ['remove_attachment' => $_POST['remove_attachment'] ?? 0], $_FILES['attachment'] ?? []);
            set_flash('success', 'لاگ حقوقی به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'به‌روزرسانی لاگ حقوقی انجام نشد.');
        }

        redirect('contracts/show/' . (int) $log['contract_id']);
    }

    public function deleteLegalLog($id)
    {
        Auth::requireLogin();
        $this->onlyPost();

        $log = LegalCaseLog::find((int) $id);
        if (!$log) {
            set_flash('error', 'لاگ حقوقی پیدا نشد.');
            redirect('contracts');
        }

        $contract = Contract::find((int) $log['contract_id']);
        $this->authorizeContractAccess($contract);
        $user = Auth::user();
        $currentLegalCase = LegalCase::latestForContract((int) $log['contract_id']);

        if (!$this->canDeleteLegalLog($user, $contract, $log, $currentLegalCase)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        if (!ConfirmationCode::verify('legal_log_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تایید حذف لاگ حقوقی درست وارد نشده است.');
            redirect('contracts/show/' . (int) $log['contract_id']);
        }

        try {
            LegalCaseLog::deleteLog((int) $id);
            set_flash('success', 'لاگ حقوقی حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'حذف لاگ حقوقی انجام نشد.');
        }

        redirect('contracts/show/' . (int) $log['contract_id']);
    }

    public function legalAttachment($id)
    {
        Auth::requireLogin();
        $log = LegalCaseLog::find((int) $id);
        if (!$log || empty($log['attachment_path'])) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }
        if (!is_staff_role(Auth::role())) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            return;
        }

        $contract = Contract::find((int) $log['contract_id']);
        $this->authorizeContractAccess($contract);

        $path = UploadHelper::absolutePath($log['attachment_path']);
        if (!$path) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }

        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function referLegal($contractId)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();

        $contract = Contract::find((int) $contractId);
        if (!$contract) {
            set_flash('error', 'قرارداد پیدا نشد.');
            redirect('contracts');
        }

        $currentLegalCase = LegalCase::latestForContract((int) $contractId);
        if (!$this->canReferToLegal(Auth::user(), $contract, $currentLegalCase)) {
            set_flash('error', 'امکان ارجاع این قرارداد به واحد حقوقی در حال حاضر وجود ندارد.');
            redirect('contracts/show/' . (int) $contractId);
        }

        try {
            $lawyerId = !empty($_POST['lawyer_id']) ? (int) $_POST['lawyer_id'] : null;
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $reason = trim((string) ($_POST['stage'] ?? '')) ?: 'ارجاع به واحد حقوقی';
            LegalCase::createCase($lawyerId, (int) $contractId, $notes, $reason);
            $case = LegalCase::latestForContract((int) $contractId);
            LegalCaseLog::createLog([
                'contract_id' => (int) $contractId,
                'legal_case_id' => $case['id'] ?? null,
                'action_stage' => 'ارجاع به واحد حقوقی',
                'action_title' => trim((string) ($_POST['action_title'] ?? '')) ?: 'ارجاع پرونده به واحد حقوقی',
                'description' => $notes,
                'action_date' => date('Y-m-d'),
                'action_time' => date('H:i'),
                'registered_by' => Auth::id(),
                'assigned_lawyer_id' => $lawyerId,
                'next_status' => 'referred',
                'cost_amount' => 0,
                'cost_type' => '',
            ]);
            set_flash('success', 'قرارداد به واحد حقوقی ارجاع شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ارجاع به واحد حقوقی انجام نشد.');
        }

        redirect('contracts/show/' . (int) $contractId);
    }

    public function generateDocument($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            ContractDocument::generate((int) $id, Auth::id());
            set_flash('success', 'متن قرارداد تولید شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تولید متن قرارداد انجام نشد.');
        }
        redirect('contracts/show/' . (int) $id);
    }

    public function saveDocument($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            ContractDocument::saveRenderedBody((int) $id, $_POST['rendered_body'] ?? '', Auth::id(), $_POST['change_reason'] ?? '', $_POST['rendered_title'] ?? '', $_POST['rendered_header'] ?? '');
            set_flash('success', 'نسخه نهایی قرارداد ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ذخیره متن قرارداد انجام نشد.');
        }
        redirect('contracts/show/' . (int) $id);
    }

    public function printDocument($id)
    {
        Auth::requireLogin();
        $contract = Contract::find((int) $id);
        $this->authorizeContractAccess($contract);
        $document = ContractDocument::document((int) $id);
        $settings = Settings::allKeyed();
        $this->render('contracts/print', [
            'title' => 'چاپ قرارداد',
            'contract' => $contract,
            'settings' => $settings,
            'documentTitle' => trim((string) ($document['rendered_title'] ?? '')) ?: ContractDocument::renderTitle((int) $id),
            'documentHeader' => trim((string) ($document['rendered_header'] ?? '')) ?: ContractDocument::renderHeader((int) $id),
            'body' => $document['rendered_body'] ?? ContractDocument::render((int) $id),
        ], null);
    }

    public function preview()
    {
        $this->requireRole('admin');
        $preview = FinanceHelper::contractPreview(
            $_GET['principal_amount'] ?? 0,
            $_GET['down_payment_amount'] ?? 0,
            $_GET['months'] ?? 6,
            $_GET['monthly_interest_rate'] ?? 0,
            ($_GET['interest_type'] ?? 'compound') === 'simple' ? 'simple' : 'compound'
        );
        $preview['formatted'] = [
            'principal_amount' => money_toman($preview['principal_amount']),
            'down_payment_amount' => money_toman($preview['down_payment_amount']),
            'financed_amount' => money_toman($preview['financed_amount']),
            'installment_amount' => money_toman($preview['installment_amount']),
            'total_payable' => money_toman($preview['total_payable']),
        ];
        $this->json(['ok' => true, 'preview' => $preview]);
    }

    public function searchCustomers()
    {
        $this->requireRole('admin');
        $query = trim(to_english_digits((string) ($_GET['q'] ?? '')));
        if (mb_strlen($query, 'UTF-8') < 2) {
            $this->json(['ok' => true, 'items' => []]);
        }
        $items = array_map(function ($customer) {
            return [
                'id' => (int) $customer['id'],
                'customer_number' => (string) $customer['id'],
                'full_name' => $customer['full_name'] ?? '',
                'mobile' => $this->maskValue($customer['mobile'] ?? '', 4, 4),
                'national_id' => $this->maskValue($customer['national_id'] ?? '', 2, 2),
                'status_label' => status_label($customer['status'] ?? ''),
                'status' => $customer['status'] ?? '',
            ];
        }, User::searchCustomers($query, 10));
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function checkCustomerIdentity()
    {
        $this->requireRole('admin');
        $nationalId = trim(to_english_digits((string) ($_GET['national_id'] ?? '')));
        $mobile = trim((string) ($_GET['mobile'] ?? ''));
        $email = trim((string) ($_GET['email'] ?? ''));
        $matches = User::identityMatches($nationalId, $mobile, $email);
        $items = array_map(function ($match) {
            return [
                'id' => (int) $match['id'],
                'full_name' => $match['full_name'] ?? '',
                'mobile' => $this->maskValue($match['mobile'] ?? '', 4, 4),
                'national_id' => $this->maskValue($match['national_id'] ?? '', 2, 2),
                'status' => $match['status'] ?? '',
                'status_label' => status_label($match['status'] ?? ''),
                'match_types' => $match['match_types'] ?? [],
            ];
        }, $matches);
        $ids = array_values(array_unique(array_map(function ($item) {
            return (int) $item['id'];
        }, $items)));
        $matchTypes = [];
        foreach ($items as $item) {
            $matchTypes = array_merge($matchTypes, $item['match_types']);
        }
        $matchTypes = array_values(array_unique($matchTypes));
        $conflict = count($ids) > 1;
        $message = 'مشتری با این مشخصات پیدا نشد و پس از ثبت قرارداد، مشتری جدید ایجاد خواهد شد.';
        if ($conflict) {
            $message = 'اطلاعات واردشده با بیش از یک مشتری موجود تطابق دارد. لطفاً اطلاعات را بررسی کنید.';
        } elseif ($items) {
            $message = in_array('national_id', $matchTypes, true)
                ? 'این کد ملی قبلاً در سامانه ثبت شده است.'
                : (in_array('mobile', $matchTypes, true)
                    ? 'این شماره موبایل برای مشتری دیگری ثبت شده است. اطلاعات را بررسی کنید.'
                    : 'این ایمیل قبلاً برای یک کاربر ثبت شده است.');
        }
        $this->json([
            'ok' => true,
            'exact' => in_array('national_id', $matchTypes, true),
            'conflict' => $conflict,
            'match_type' => $matchTypes[0] ?? null,
            'message' => $message,
            'items' => $items,
        ]);
    }

    public function checkIdentity()
    {
        $this->checkCustomerIdentity();
    }

    public function searchGuarantors()
    {
        $this->requireRole('admin');
        $query = trim(to_english_digits((string) ($_GET['q'] ?? '')));
        if (mb_strlen($query, 'UTF-8') < 2) {
            $this->json(['ok' => true, 'items' => []]);
        }
        $items = array_map(function ($customer) {
            return [
                'id' => (int) $customer['id'],
                'customer_number' => (string) $customer['id'],
                'full_name' => $customer['full_name'] ?? '',
                'mobile' => $customer['mobile'] ?? '',
                'national_id' => $customer['national_id'] ?? '',
                'status' => $customer['status'] ?? '',
            ];
        }, User::searchActiveCustomers($query, 12));
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function search()
    {
        $this->requireRole(['admin', 'operator']);
        $query = trim(to_english_digits((string) ($_GET['q'] ?? '')));
        if (mb_strlen($query, 'UTF-8') < 2) {
            $this->json(['ok' => true, 'items' => []]);
        }
        $items = array_map(function ($contract) {
            return [
                'id' => (int) $contract['id'],
                'contract_number' => $contract['contract_number'] ?? '',
                'customer_name' => $contract['customer_name'] ?? '',
                'mobile' => $contract['mobile'] ?? '',
                'national_id' => $contract['national_id'] ?? '',
                'status' => $contract['status'] ?? '',
                'status_label' => status_label($contract['status'] ?? ''),
                'operator_name' => $contract['operator_name'] ?? '',
                'label' => trim(($contract['contract_number'] ?? '') . ' - ' . ($contract['customer_name'] ?? '') . ' - ' . to_persian_digits($contract['mobile'] ?? '')),
            ];
        }, Contract::search($query, 12, [
            'eligible_legal' => ($_GET['eligible'] ?? '') === 'legal',
            'without_open_legal_case' => ($_GET['without_open_case'] ?? '') === '1',
        ]));
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function booklet($id)
    {
        Auth::requireLogin();
        $contract = Contract::find((int) $id);
        if (!$contract) {
            set_flash('error', 'قرارداد پیدا نشد.');
            redirect('contracts');
        }
        if (Auth::role() === 'customer' && (int) $contract['customer_id'] !== (int) Auth::id()) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        if (!in_array(Auth::role(), ['admin', 'operator', 'customer'], true)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        $this->render('contracts/booklet', [
            'title' => 'دفترچه اقساط',
            'contract' => $contract,
            'guarantors' => Contract::guarantors((int) $id),
            'installments' => Installment::all(['contract_id' => (int) $id, 'custom_last' => true]),
        ], null);
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (!ConfirmationCode::verify('contract_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تأیید حذف قرارداد درست وارد نشده است.');
            redirect('contracts');
        }
        try {
            Contract::deleteContract((int) $id);
            set_flash('success', 'قرارداد حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'حذف قرارداد انجام نشد.');
        }
        redirect('contracts');
    }

    public function cancel($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (empty($_POST['confirm_cancel'])) {
            set_flash('error', 'برای لغو قرارداد باید تأیید نهایی را فعال کنید.');
            redirect('contracts');
        }
        try {
            Contract::cancel((int) $id, $_POST['cancellation_reason'] ?? '', Auth::id());
            set_flash('success', 'قرارداد و اقساط فعال آن با موفقیت لغو شدند. سوابق مالی و تاریخی حفظ شده است.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'لغو قرارداد انجام نشد.');
        }
        redirect('contracts');
    }

    protected function resolveCustomer(&$reusedCustomer = null)
    {
        if (!empty($_POST['customer_id'])) {
            $customer = User::find((int) $_POST['customer_id']);
            if (!$customer || ($customer['role'] ?? '') !== 'customer') {
                throw new InvalidArgumentException('مشتری انتخاب‌شده معتبر نیست.');
            }
            return (int) $customer['id'];
        }
        if (trim($_POST['new_customer_full_name'] ?? '') === '') {
            throw new InvalidArgumentException('مشتری موجود را انتخاب کنید یا اطلاعات مشتری جدید را کامل کنید.');
        }
        $payload = [
            'role' => 'customer',
            'username' => null,
            'full_name' => $_POST['new_customer_full_name'],
            'father_name' => $_POST['new_customer_father_name'] ?? '',
            'issued_from' => $_POST['new_customer_issued_from'] ?? '',
            'national_id' => $_POST['new_customer_national_id'] ?? '',
            'mobile' => $_POST['new_customer_mobile'] ?? '',
            'secondary_phone' => $_POST['new_customer_secondary_phone'] ?? '',
            'address' => $_POST['new_customer_address'] ?? '',
            'email' => $_POST['new_customer_email'] ?? '',
            'password' => '',
            'status' => 'active',
        ];
        $validator = (new Validator($payload))->mobile('mobile', 'موبایل')->nationalId('national_id', 'کد ملی')->email('email', 'ایمیل');
        if (!$validator->passes()) {
            throw new InvalidArgumentException(implode(' ', $validator->errors()));
        }
        $matches = User::identityMatches($payload['national_id'], $payload['mobile'], $payload['email']);
        $matchIds = array_values(array_unique(array_map(function ($match) {
            return (int) $match['id'];
        }, $matches)));
        if (count($matchIds) > 1) {
            throw new InvalidArgumentException('اطلاعات واردشده با بیش از یک مشتری موجود تطابق دارد. لطفاً اطلاعات را بررسی کنید.');
        }
        if ($matches) {
            $existing = $matches[0];
            $matchTypes = $existing['match_types'] ?? [];
            if ($payload['national_id'] !== '' && !in_array('national_id', $matchTypes, true) && in_array('mobile', $matchTypes, true)) {
                throw new InvalidArgumentException('این شماره موبایل برای مشتری دیگری ثبت شده است. اطلاعات را بررسی کنید.');
            }
            if (!in_array('national_id', $matchTypes, true) && in_array('email', $matchTypes, true)) {
                throw new InvalidArgumentException('این ایمیل قبلاً برای یک کاربر ثبت شده است. برای جلوگیری از ادغام اشتباه، مشتری موجود را جداگانه انتخاب کنید.');
            }
            $reusedCustomer = $existing['full_name'] ?? 'مشتری موجود';
            return (int) $existing['id'];
        }
        return User::create($payload);
    }

    protected function maskValue($value, $prefix = 2, $suffix = 2)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $length = strlen($value);
        if ($length <= ($prefix + $suffix)) {
            return str_repeat('*', $length);
        }
        return substr($value, 0, $prefix) . str_repeat('*', max(3, $length - $prefix - $suffix)) . substr($value, -$suffix);
    }

    protected function buildContractUpdatePayload(array $contract, array $input)
    {
        $customerId = null;
        if (array_key_exists('customer_id', $input) && trim((string) $input['customer_id']) !== '') {
            $customerId = (int) $input['customer_id'];
            $customer = User::find($customerId);
            if (!$customer || ($customer['role'] ?? '') !== 'customer') {
                throw new InvalidArgumentException('مشتری انتخاب‌شده معتبر نیست.');
            }
        } else {
            $customerId = (int) ($contract['customer_id'] ?? 0);
        }

        $startDate = parse_jalali_date($input['start_date'] ?? '') ?: ($contract['start_date'] ?? date('Y-m-d'));
        $firstDue = parse_jalali_date($input['first_due_date'] ?? '') ?: ($contract['first_due_date'] ?? FinanceHelper::addMonths($startDate, 1));

        $assignedOperatorId = null;
        if (array_key_exists('assigned_operator_id', $input)) {
            $rawOperator = trim((string) $input['assigned_operator_id']);
            if ($rawOperator !== '') {
                $assignedOperatorId = (int) $rawOperator;
                $operator = User::find($assignedOperatorId);
                if (!$operator || !in_array($operator['role'] ?? '', ['admin', 'operator', 'lawyer'], true)) {
                    throw new InvalidArgumentException('اپراتور انتخاب‌شده معتبر نیست.');
                }
            }
        } elseif (array_key_exists('assigned_operator_id', $contract)) {
            $assignedOperatorId = $contract['assigned_operator_id'] !== null ? (int) $contract['assigned_operator_id'] : null;
        }

        return [
            'customer_id' => $customerId,
            'principal_amount' => $input['principal_amount'] ?? ($contract['principal_amount'] ?? 0),
            'down_payment_amount' => $input['down_payment_amount'] ?? ($contract['down_payment_amount'] ?? 0),
            'monthly_interest_rate' => $input['monthly_interest_rate'] ?? ($contract['monthly_interest_rate'] ?? 0),
            'interest_type' => (($input['interest_type'] ?? ($contract['interest_type'] ?? 'compound')) === 'simple') ? 'simple' : 'compound',
            'months' => $input['months'] ?? ($contract['months'] ?? 6),
            'start_date' => $startDate,
            'first_due_date' => $firstDue,
            'assigned_operator_id' => $assignedOperatorId,
            'notes' => array_key_exists('notes', $input) ? $input['notes'] : ($contract['notes'] ?? ''),
            'updated_by' => Auth::id(),
            'change_reason' => trim((string) ($input['change_reason'] ?? '')) ?: 'ویرایش قرارداد',
        ];
    }

    protected function authorizeContractAccess($contract)
    {
        if (!$contract) {
            set_flash('error', 'قرارداد پیدا نشد.');
            redirect('contracts');
        }
        $role = Auth::role();
        if ($role === 'admin' || $role === 'operator') {
            return;
        }
        if ($role === 'lawyer' && LegalCase::hasAccessibleCaseForLawyer((int) $contract['id'], (int) Auth::id())) {
            return;
        }
        if ($role === 'customer' && (int) $contract['customer_id'] === (int) Auth::id()) {
            return;
        }
        http_response_code(403);
        $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
        exit;
    }

    protected function canManageLegalAction($user, $contract, $currentLegalCase)
    {
        if (!$user || !$contract) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'admin' || $role === 'operator') {
            return true;
        }
        if ($role === 'lawyer') {
            return LegalCase::hasAccessibleCaseForLawyer((int) $contract['id'], (int) $user['id']);
        }
        return false;
    }

    protected function canViewLegalCosts($user, $contract, $currentLegalCase)
    {
        if (!$user || !$contract) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'lawyer') {
            return $this->canManageLegalAction($user, $contract, $currentLegalCase);
        }
        return $role === 'operator' && (int) ($user['is_department_manager'] ?? 0) === 1;
    }

    protected function canEditLegalCosts($user, $contract, $currentLegalCase)
    {
        if (!$user || !$contract) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        return $role === 'lawyer' && $this->canManageLegalAction($user, $contract, $currentLegalCase);
    }

    protected function canReferToLegal($user, $contract, $currentLegalCase)
    {
        if (!$user || !$contract) {
            return false;
        }
        if (!in_array($user['role'] ?? '', ['admin', 'operator'], true)) {
            return false;
        }
        if ($currentLegalCase && ($currentLegalCase['status'] ?? '') !== 'closed') {
            return false;
        }
        return LegalCase::contractIsEligible((int) $contract['id']);
    }

    protected function canViewFinancialSummary($user)
    {
        if (!$user || ($user['role'] ?? '') === 'customer') {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return is_staff_role($user['role'] ?? '') && (int) ($user['is_department_manager'] ?? 0) === 1;
    }

    protected function canUpdateLegalLog($user, $contract, $log, $currentLegalCase)
    {
        if (!$user || !$contract || !$log) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'lawyer') {
            return $this->canManageLegalAction($user, $contract, $currentLegalCase);
        }
        return $role === 'operator' && (int) ($log['registered_by'] ?? 0) === (int) $user['id'];
    }

    protected function canDeleteLegalLog($user, $contract, $log, $currentLegalCase)
    {
        if (!$user || !$contract || !$log) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'lawyer') {
            return $this->canManageLegalAction($user, $contract, $currentLegalCase)
                && (
                    (int) ($log['registered_by'] ?? 0) === (int) $user['id']
                    || (int) ($log['assigned_lawyer_id'] ?? 0) === (int) $user['id']
                    || empty($log['assigned_lawyer_id'])
                );
        }
        return false;
    }

    protected function buildLegalLogPayload($contractId, $currentLegalCase, $allowCostEdit)
    {
        $payload = $_POST;
        $payload['contract_id'] = (int) $contractId;
        $payload['registered_by'] = Auth::id();
        if (empty($payload['legal_case_id']) && !empty($currentLegalCase['id'])) {
            $payload['legal_case_id'] = (int) $currentLegalCase['id'];
        }
        if (empty($payload['assigned_lawyer_id']) && !empty($currentLegalCase['lawyer_id'])) {
            $payload['assigned_lawyer_id'] = (int) $currentLegalCase['lawyer_id'];
        }
        if (!$allowCostEdit) {
            $payload['cost_amount'] = 0;
            $payload['cost_type'] = '';
        }
        if (trim((string) ($payload['next_status'] ?? '')) === '' && trim((string) ($payload['action_stage'] ?? '')) === 'مختومه') {
            $payload['next_status'] = 'closed';
        }
        if (trim((string) ($payload['next_status'] ?? '')) === '' && !empty($currentLegalCase['status'])) {
            $payload['next_status'] = $currentLegalCase['status'];
        }
        return $payload;
    }
}
