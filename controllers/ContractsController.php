<?php

class ContractsController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $readOnly = Auth::role() === 'operator';
        $affiliatePlugin = class_exists('PluginRegistry') ? PluginRegistry::find('proma-affiliate') : null;
        $affiliateEnabled = !$readOnly && class_exists('PluginManager') && PluginManager::isActive('proma-affiliate')
            && version_compare((string) ($affiliatePlugin['version'] ?? '0.0.0'), '1.0.4', '>=');
        $today = date('Y-m-d');
        $viewMode = in_array($_GET['view'] ?? '', ['cards', 'list'], true) ? $_GET['view'] : 'cards';
        $result = Contract::paginated([
            'search' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? 1,
            // A card also ships chart, timeline and edit UI. Keeping card
            // pages intentionally smaller protects shared-host PHP workers
            // without reducing the denser list view.
            'per_page' => $viewMode === 'cards' ? 12 : 24,
        ]);
        $preselectedCustomer = null;
        if (!$readOnly && !empty($_GET['customer_id'])) {
            $candidateCustomer = User::find((int) $_GET['customer_id']);
            if ($candidateCustomer && ($candidateCustomer['role'] ?? '') === 'customer' && ($candidateCustomer['status'] ?? '') === 'active') {
                $preselectedCustomer = $candidateCustomer;
            }
        }
        $contractIds = array_column($result['items'], 'id');
        $this->render('contracts/index', [
            'title' => $readOnly ? 'قراردادها' : 'مدیریت قراردادها',
            'contracts' => $result['items'],
            'pagination' => $result,
            'contractStats' => Contract::installmentStatsForContracts($contractIds),
            'contractContacts' => Contract::contactDirectoryForContracts($contractIds),
            'contractTrends' => Payment::monthlyTrendsForContracts($contractIds, 6),
            'contractTimelines' => Payment::recentForContracts($contractIds, 8),
            'contractGuarantors' => Contract::guarantorsForContracts($contractIds),
            'contractItems' => $readOnly ? [] : ContractDocument::itemsForContracts($contractIds),
            'contractGuarantees' => $readOnly ? [] : ContractDocument::guaranteesForContracts($contractIds),
            'contractGuarantorPeople' => $readOnly ? [] : ContractDocument::guarantorPeopleForContracts($contractIds),
            'customers' => [],
            'preselectedCustomer' => $preselectedCustomer,
            'operators' => $readOnly ? [] : User::all('operator'),
            'salesPeople' => $readOnly ? [] : User::all(null, null, 'active', ['roles' => ['admin', 'operator', 'lawyer', 'repair_technician'], 'limit' => 100]),
            'affiliateEnabled' => $affiliateEnabled,
            'currentUserId' => (int) Auth::id(),
            'settings' => Settings::allKeyed(),
            'defaultStartDate' => jdate($today),
            'defaultFirstDueDate' => jdate(FinanceHelper::addMonths($today, 1)),
            'readOnly' => $readOnly,
            'contractsRoute' => 'contracts',
            'readOnlyTitle' => 'فهرست قراردادها',
        ], is_ajax_request() ? null : 'app');
    }

    public function export()
    {
        $this->requireRole(['admin', 'operator']);
        $search = trim((string) ($_GET['q'] ?? ''));
        SpreadsheetHelper::downloadPaginatedXlsx(
            'قراردادها.xlsx',
            'قراردادها',
            ['شماره قرارداد', 'مشتری', 'کد ملی', 'موبایل', 'اپراتور', 'مبلغ قرارداد (تومان)', 'پیش‌پرداخت (تومان)', 'تعداد اقساط', 'وضعیت', 'تاریخ قرارداد', 'اولین سررسید', 'تاریخ ثبت'],
            static fn (int $page, int $perPage): array => Contract::paginated(['search' => $search, 'page' => $page, 'per_page' => min(60, $perPage)]),
            static fn (array $row): array => [
                (string) ($row['contract_number'] ?? ''), (string) ($row['customer_name'] ?? ''),
                (string) ($row['national_id'] ?? ''), (string) ($row['mobile'] ?? ''),
                (string) ($row['operator_name'] ?? ''), normalize_money($row['principal_amount'] ?? 0),
                normalize_money($row['down_payment_amount'] ?? 0), (int) ($row['months'] ?? 0),
                status_label($row['status'] ?? ''), jdate($row['start_date'] ?? ''),
                jdate($row['first_due_date'] ?? ''), (string) ($row['created_at'] ?? ''),
            ]
        );
    }

    /**
     * Detail is intentionally loaded only when the destructive-confirmation
     * dialog is opened. Rendering these summaries for every visible contract
     * caused hundreds of dependency queries on a single card page.
     */
    public function cancellationSummary($id)
    {
        $this->requireRole('admin');
        $contract = Contract::find((int) $id);
        if (!$contract) {
            $this->json(['ok' => false, 'message' => 'قرارداد پیدا نشد.'], 404);
        }
        $this->json([
            'ok' => true,
            'summary' => Contract::cancellationSummary((int) $id),
        ]);
    }

    public function deletionPreview($id)
    {
        $this->requireRole('admin');
        try {
            $this->json([
                'ok' => true,
                'preview' => Contract::deletionPreview((int) $id),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function duplicates()
    {
        $this->requireRole('admin');
        $groups = ContractDuplicateRepairService::scan();
        $this->render('contracts/duplicates', [
            'title' => 'بررسی قراردادهای تکراری',
            'groups' => $groups,
        ]);
    }

    public function repairDuplicates()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (empty($_POST['confirm_repair'])) {
            set_flash('error', 'برای آرشیو قراردادهای تکراری باید تأیید نهایی را فعال کنید.');
            redirect('contracts/duplicates');
        }
        try {
            $count = ContractDuplicateRepairService::archiveDuplicates(
                (int) ($_POST['canonical_id'] ?? 0),
                $_POST['duplicate_ids'] ?? [],
                Auth::id()
            );
            set_flash('success', to_persian_digits($count) . ' قرارداد تکراری بدون حذف سوابق آرشیو شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ترمیم قراردادهای تکراری انجام نشد.');
        }
        redirect('contracts/duplicates');
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $reusedCustomer = null;
        $requestUuid = ContractRequest::normalizeUuid($_POST['contract_request_uuid'] ?? '');
        try {
            $requestState = ContractRequest::beginRequest($requestUuid, Auth::id(), ContractRequest::hash($_POST));
            if (($requestState['status'] ?? '') === 'completed') {
                set_flash('success', 'این قرارداد قبلاً ثبت شده بود و از ایجاد نسخه تکراری جلوگیری شد.');
                redirect('contracts/show/' . (int) $requestState['contract_id']);
            }
            $customerId = $this->resolveCustomer($reusedCustomer);
            $sellerUserId = $this->resolveAttributionUserId($_POST['seller_user_id'] ?? (string) Auth::id(), 'فروشنده', false);
            $marketerUserId = $this->resolveAttributionUserId($_POST['marketer_user_id'] ?? '', 'بازاریاب', true);
            $startDate = parse_jalali_date($_POST['start_date'] ?? '') ?: date('Y-m-d');
            $firstDue = parse_jalali_date($_POST['first_due_date'] ?? '') ?: FinanceHelper::addMonths($startDate, 1);
            if (!$customerId || !$startDate || !$firstDue) {
                throw new InvalidArgumentException('اطلاعات مشتری و تاریخ‌های قرارداد باید کامل و معتبر باشد.');
            }
            $createdContractId = (int) Contract::createWithInstallments([
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
                'seller_user_id' => $sellerUserId,
                'marketer_user_id' => $marketerUserId,
                'notes' => $_POST['notes'] ?? '',
                'created_by' => Auth::id(),
                'request_uuid' => $requestUuid,
            ], $_POST['guarantors'] ?? [], $_POST['items'] ?? [], $_POST['guarantee'] ?? [], $_POST['guarantor_people'] ?? []);
            if (class_exists('CreditRiskEngine')) CreditRiskEngine::invalidate($customerId);
            $affiliateReferrerId = (int) ($_POST['affiliate_referrer_id'] ?? 0);
            $affiliatePlugin = class_exists('PluginRegistry') ? PluginRegistry::find('proma-affiliate') : null;
            if ($createdContractId > 0 && class_exists('PluginManager')
                && PluginManager::isActive('proma-affiliate') && version_compare((string) ($affiliatePlugin['version'] ?? '0.0.0'), '1.0.5', '>=')) {
                if ($affiliateReferrerId > 0) {
                    try {
                        PluginHooks::dispatch('affiliate.contract.sale_selected', [
                            'contract_id' => $createdContractId,
                            'customer_id' => $customerId,
                            'referrer_user_id' => $affiliateReferrerId,
                        ]);
                    } catch (Throwable $affiliateError) {
                        if (class_exists('PluginRegistry')) {
                            PluginRegistry::logRuntimeError('affiliate.contract.sale_selected', $affiliateError);
                        } else {
                            ErrorHandler::log('affiliate.contract.sale_selected', $affiliateError, 500);
                        }
                    }
                }
                try {
                    PluginHooks::dispatch('affiliate.contract.created', ['contract_id' => $createdContractId, 'customer_id' => $customerId]);
                } catch (Throwable $affiliateError) {
                    if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('affiliate.contract.created', $affiliateError);
                    else ErrorHandler::log('affiliate.contract.created', $affiliateError, 500);
                }
            }
            $message = 'قرارداد و اقساط آن با موفقیت ساخته شد.';
            if ($reusedCustomer) {
                $message .= ' مشتری «' . $reusedCustomer . '» از قبل وجود داشت و همان پرونده برای قرارداد انتخاب شد.';
            }
            set_flash('success', $message);
        } catch (Throwable $e) {
            ContractRequest::fail($requestUuid, $e);
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
        $contractDocument = ContractDocument::viewModel($contractId);
        $amendmentSchemaReady = ContractAmendmentService::isAvailable();
        $amendmentWorkspace = $amendmentSchemaReady
            ? ContractAmendmentService::viewModel($contractId)
            : ['current_version' => null, 'versions' => [], 'amendments' => [], 'schedule_editor' => [], 'migration_required' => true];

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
        $legalCostSummary = $canViewLegalCosts ? ContractLegalCostSummaryService::forContract($contractId) : [];
        $legalCosts = $canViewLegalCosts ? LegalCaseCostService::forContract($contractId) : [];

        $installmentBatch = ContractInstallmentFinancialBatchService::load($contractId, date('Y-m-d'));
        $installments = $installmentBatch['items'];
        $settlementPreview = PaymentAllocationService::quote($installments, date('Y-m-d'));
        $financialSummary = $canViewFinancialSummary
            ? ContractFinancialSummaryService::summarize($contractId, date('Y-m-d'), $installments)
            : null;
        $canViewOperatorDebtScenarios = $canViewFinancialSummary
            && (Auth::role() === 'admin' || Auth::role() === 'operator');
        $operatorDebtCards = $canViewOperatorDebtScenarios && $financialSummary
            ? OperatorDebtScenarioService::cards($financialSummary, !empty($financialSummary['actual_legal_referral']))
            : [];

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
            'document' => $contractDocument['document'],
            'documentVersions' => ContractDocument::versions($contractId),
            'documentTitle' => $contractDocument['title'],
            'documentHeader' => $contractDocument['header'],
            'items' => ContractDocument::items($contractId),
            'guarantees' => ContractDocument::guarantees($contractId),
            'guarantors' => $contractDocument['guarantors'],
            'installments' => $installments,
            'installmentBatch' => $installmentBatch,
            'settlementPreview' => $settlementPreview,
            'paymentTimeline' => Payment::recentForContract($contractId, 8),
            'logs' => ContractDocument::logs($contractId),
            'canManageDocument' => Auth::role() === 'admin',
            'amendmentWorkspace' => $amendmentWorkspace,
            'canManageAmendments' => Auth::role() === 'admin',
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
            'operatorDebtCards' => $operatorDebtCards,
            'financialSummaryDate' => date('Y-m-d'),
            'legalLogCostTotal' => $canViewLegalCosts ? LegalCaseLog::costTotalForContract($contractId) : 0,
            'legacyLegalCostTotal' => $canViewLegalCosts ? LegalCase::expenseTotalForContract($contractId) : 0,
            'legalCostSummary' => $legalCostSummary,
            'legalCosts' => $legalCosts,
            'editableLegalLogIds' => $editableLogIds,
            'deletableLegalLogIds' => $deletableLogIds,
            'cancellationSummary' => Contract::cancellationSummary($contractId),
        ]);
    }

    public function changeInstallment($contractId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        try {
            $result = InstallmentChangeService::change((int) ($_POST['installment_id'] ?? 0), $_POST, Auth::id(), (int) $contractId);
            set_flash('success', 'درخواست تغییر قسط در گردش اصلاحیه قرارداد ثبت شد و تا تأیید مدیر دوم اثری مالی ندارد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش امن قسط انجام نشد.');
        }
        redirect('contracts/show/' . (int) $contractId);
    }

    public function requestAmendment($contractId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        try {
            $result = ContractAmendmentService::request((int) $contractId, $_POST, (int) Auth::id());
            set_flash('success', 'درخواست اصلاحیه شماره ' . to_persian_digits($result['amendment_number']) . ' ثبت شد؛ شرایط قرارداد هنوز تغییر نکرده است.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت درخواست اصلاحیه انجام نشد.');
        }
        redirect('contracts/show/' . (int) $contractId . '#contract-amendments');
    }

    public function previewAmendment($contractId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        try {
            $this->json(['ok' => true, 'preview' => ContractAmendmentService::preview((int) $contractId, $_POST)]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'پیش‌نمایش اصلاحیه انجام نشد.'], $e instanceof InvalidArgumentException ? 422 : 500);
        }
    }

    public function approveAmendment($amendmentId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $amendment = Model::fetch('SELECT contract_id FROM contract_amendments WHERE id = ?', [(int) $amendmentId]);
        $contract = $amendment ? Contract::find((int) $amendment['contract_id']) : null;
        $this->authorizeContractAccess($contract);
        try {
            $result = ContractAmendmentService::approve((int) $amendmentId, (int) Auth::id());
            set_flash(($result['status'] ?? '') === 'conflict' ? 'error' : 'success', ($result['status'] ?? '') === 'conflict'
                ? 'نسخه پایه تغییر کرده است؛ درخواست قبلی برای جلوگیری از تأیید منسوخ قفل شد.'
                : 'تأیید داخلی ثبت شد. هنوز هیچ تغییر مالی اعمال نشده است.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تأیید اصلاحیه انجام نشد.');
        }
        redirect('contracts/show/' . (int) ($amendment['contract_id'] ?? 0) . '#contract-amendments');
    }

    public function rejectAmendment($amendmentId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $amendment = Model::fetch('SELECT contract_id FROM contract_amendments WHERE id = ?', [(int) $amendmentId]);
        $contract = $amendment ? Contract::find((int) $amendment['contract_id']) : null;
        $this->authorizeContractAccess($contract);
        try {
            ContractAmendmentService::reject((int) $amendmentId, (int) Auth::id(), $_POST['rejection_reason'] ?? '');
            set_flash('success', 'رد اصلاحیه ثبت شد و شرایط قرارداد تغییر نکرد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'رد اصلاحیه انجام نشد.');
        }
        redirect('contracts/show/' . (int) ($amendment['contract_id'] ?? 0) . '#contract-amendments');
    }

    public function applyAmendment($amendmentId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $amendment = Model::fetch('SELECT contract_id FROM contract_amendments WHERE id = ?', [(int) $amendmentId]);
        $contract = $amendment ? Contract::find((int) $amendment['contract_id']) : null;
        $this->authorizeContractAccess($contract);
        try {
            $result = ContractAmendmentService::apply((int) $amendmentId, (int) Auth::id());
            set_flash(($result['status'] ?? '') === 'conflict' ? 'error' : 'success', ($result['status'] ?? '') === 'conflict'
                ? 'پرداخت، تخصیص یا نسخه مالی پس از تأیید تغییر کرده است؛ اصلاحیه اعمال نشد و باید دوباره بررسی شود.'
                : 'اصلاحیه در تراکنش ثبت و نسخه مالی جدید ایجاد شد. این ثبت، امضای طرف قرارداد را اثبات نمی‌کند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'اعمال اصلاحیه انجام نشد.');
        }
        redirect('contracts/show/' . (int) ($amendment['contract_id'] ?? 0) . '#contract-amendments');
    }

    public function cancelAmendment($amendmentId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $amendment = Model::fetch('SELECT contract_id FROM contract_amendments WHERE id = ?', [(int) $amendmentId]);
        $contract = $amendment ? Contract::find((int) $amendment['contract_id']) : null;
        $this->authorizeContractAccess($contract);
        try {
            ContractAmendmentService::cancel((int) $amendmentId, (int) Auth::id(), $_POST['cancel_reason'] ?? '');
            set_flash('success', 'لغو اصلاحیه ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'لغو اصلاحیه انجام نشد.');
        }
        redirect('contracts/show/' . (int) ($amendment['contract_id'] ?? 0) . '#contract-amendments');
    }

    public function voidInstallment($contractId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        try {
            $result = InstallmentChangeService::void((int) ($_POST['installment_id'] ?? 0), $_POST, Auth::id(), (int) $contractId);
            set_flash('success', 'درخواست ابطال قسط به‌صورت اصلاحیه ثبت شد؛ خود قسط تا تأیید و اعمال تغییر می‌کند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ابطال امن قسط انجام نشد.');
        }
        redirect('contracts/show/' . (int) $contractId);
    }

    public function approveLegalCost($contractId, $costId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        try {
            $cost = Model::fetch('SELECT id FROM legal_case_costs WHERE id = ? AND contract_id = ?', [(int) $costId, (int) $contractId]);
            if (!$cost) throw new InvalidArgumentException('هزینه حقوقی متعلق به این قرارداد نیست.');
            LegalCaseCostService::approve((int) $costId, (int) Auth::id());
            set_flash('success', 'هزینه حقوقی تأیید شد و در مبلغ قابل مطالبه قرارداد وارد شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تأیید هزینه حقوقی انجام نشد.');
        }
        redirect('contracts/show/' . (int) $contractId . '#legal');
    }

    public function reverseLegalCost($contractId, $costId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $contract = Contract::find((int) $contractId);
        $this->authorizeContractAccess($contract);
        try {
            $cost = Model::fetch('SELECT id FROM legal_case_costs WHERE id = ? AND contract_id = ?', [(int) $costId, (int) $contractId]);
            if (!$cost) throw new InvalidArgumentException('هزینه حقوقی متعلق به این قرارداد نیست.');
            LegalCaseCostService::reverse((int) $costId, (string) ($_POST['reason'] ?? ''), (int) Auth::id());
            set_flash('success', 'برگشت هزینه حقوقی با حفظ تاریخچه ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'برگشت هزینه حقوقی انجام نشد.');
        }
        redirect('contracts/show/' . (int) $contractId . '#legal');
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

    public function publishDocumentVersion($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            ContractDocument::publishVersion((int) $id, Auth::id());
            set_flash('success', 'نسخه قرارداد منتشر شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'انتشار نسخه قرارداد انجام نشد.');
        }
        redirect('contracts/show/' . (int) ($_POST['contract_id'] ?? 0));
    }

    public function finalizeDocumentVersion($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            ContractDocument::finalizeVersion((int) $id, Auth::id());
            set_flash('success', 'نسخه قرارداد نهایی شد و دیگر با تولید خودکار بازنویسی نمی‌شود.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'نهایی‌سازی نسخه قرارداد انجام نشد.');
        }
        redirect('contracts/show/' . (int) ($_POST['contract_id'] ?? 0));
    }

    public function paymentGroup($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        try {
            $requestedScope = (string) ($_POST['settlement_scope'] ?? 'selected');
            $settlementScope = !empty($_POST['include_legal_costs'])
                ? 'contract_legal_first'
                : (in_array($requestedScope, ['contract', 'contract_legal_first', 'schedule'], true) ? $requestedScope : 'selected');
            $group = PaymentGroupService::create(
                (int) $id,
                $_POST['installment_ids'] ?? [],
                $_POST['group_amount'] ?? 0,
                Auth::id(),
                $_POST['payment_method'] ?? 'manual',
                $_POST['group_description'] ?? '',
                false,
                $_POST['payment_request_uuid'] ?? null,
                $_POST['quote_uuid'] ?? null,
                parse_jalali_date($_POST['payment_date'] ?? '') ?: date('Y-m-d'),
                $_POST['payment_time'] ?? date('H:i'),
                $settlementScope
            );
            set_flash('success', 'پرداخت گروهی ' . ($group['group_number'] ?? '') . ' ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'پرداخت گروهی انجام نشد.');
        }
        redirect('contracts/show/' . (int) $id);
    }

    public function bulkInstallmentAction($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $bulkAction = $_POST['bulk_action'] ?? '';
            if ($bulkAction === 'payment_group') {
                $settlementScope = !empty($_POST['include_legal_costs'])
                    ? 'contract_legal_first'
                    : (($_POST['settlement_scope'] ?? 'selected') === 'schedule' ? 'schedule' : 'selected');
                $group = PaymentGroupService::create(
                    (int) $id,
                    $_POST['installment_ids'] ?? [],
                    $_POST['group_amount'] ?? 0,
                    Auth::id(),
                    $_POST['payment_method'] ?? 'manual',
                    $_POST['group_description'] ?? '',
                    false,
                    $_POST['payment_request_uuid'] ?? null,
                    $_POST['quote_uuid'] ?? null,
                    parse_jalali_date($_POST['payment_date'] ?? '') ?: date('Y-m-d'),
                    $_POST['payment_time'] ?? date('H:i'),
                    $settlementScope
                );
                $result = ['updated' => count($_POST['installment_ids'] ?? []), 'group_number' => $group['group_number'] ?? ''];
            } else {
                $result = Installment::bulkAction((int) $id, $_POST['installment_ids'] ?? [], $bulkAction, $_POST['bulk_reason'] ?? '', Auth::id());
            }
            set_flash('success', $bulkAction === 'payment_group' ? 'پرداخت گروهی ' . ($result['group_number'] ?? '') . ' ثبت شد.' : 'عملیات دسته‌جمعی روی ' . to_persian_digits($result['updated']) . ' قسط اعمال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'عملیات دسته‌جمعی اقساط انجام نشد.');
        }
        redirect('contracts/show/' . (int) $id);
    }

    public function settlementQuote($id)
    {
        Auth::requireLogin();
        $contract = Contract::find((int) $id);
        $this->authorizeContractAccess($contract);
        $scope = in_array(($_GET['scope'] ?? 'selected'), ['contract', 'contract_legal_first', 'schedule'], true) ? (string) $_GET['scope'] : 'selected';
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['installment_ids'] ?? [])))));
        if ($scope === 'selected' && !$ids) {
            $this->json(['ok' => false, 'message' => 'حداقل یک قسط را انتخاب کنید.'], 422);
        }
        try {
            $quote = SettlementQuoteService::create((int) $contract['id'], in_array($scope, ['contract', 'contract_legal_first', 'schedule'], true) ? [] : $ids, Auth::id(), $scope);
            $this->json(['ok' => true, 'quote' => $quote]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'محاسبه پیش‌فاکتور تسویه انجام نشد.'], $e->getCode() === 409 ? 409 : 422);
        }
    }

    public function printDocument($id)
    {
        Auth::requireLogin();
        $contract = Contract::find((int) $id);
        $this->authorizeContractAccess($contract);
        $contractDocument = ContractDocument::viewModel((int) $id);
        $settings = Settings::allKeyed();
        $printBody = ContractDocument::injectSellerSignature($contractDocument['body'], $settings, $contract);
        $documentTitle = $contractDocument['title'];
        $documentHeader = $contractDocument['header'];
        if (ContractDocument::hasUnconsentedAmendment((int) $id)) {
            $documentTitle .= ' — نسخه پیشین پس از اصلاحیه داخلی';
            $documentHeader = trim($documentHeader . "\n" . 'هشدار: اصلاحیه مالی با تأیید داخلی اعمال شده است؛ رضایت طرف قرارداد در سامانه ثبت نشده و این سند نسخه پیشین است.');
        }
        $this->render('contracts/print', [
            'title' => 'چاپ قرارداد',
            'contract' => $contract,
            'settings' => $settings,
            'printProfile' => ContractPrintProfile::load($settings),
            'documentTitle' => $documentTitle,
            'documentHeader' => $documentHeader,
            'body' => $printBody,
        ], null);
    }

    public function printDocumentVersion($id)
    {
        Auth::requireLogin();
        $version = Model::fetch('SELECT * FROM contract_document_versions WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$version) {
            http_response_code(404);
            exit('نسخه سند قرارداد پیدا نشد.');
        }
        if (($version['source'] ?? '') === 'amendment_draft') $this->requireRole('admin');
        $contract = Contract::find((int) $version['contract_id']);
        $this->authorizeContractAccess($contract);
        $settings = Settings::allKeyed();
        $body = (string) ($version['rendered_body'] ?? '');
        if (($version['source'] ?? '') !== 'amendment_draft') {
            $body = ContractDocument::injectSellerSignature($body, $settings, $contract);
        }
        $this->render('contracts/print', [
            'title' => ($version['source'] ?? '') === 'amendment_draft' ? 'پیش‌نویس داخلی الحاقیه' : 'چاپ نسخه قرارداد',
            'contract' => $contract,
            'settings' => $settings,
            'printProfile' => ContractPrintProfile::load($settings),
            'documentTitle' => (string) ($version['rendered_title'] ?? 'نسخه قرارداد'),
            'documentHeader' => (string) ($version['rendered_header'] ?? ''),
            'body' => $body,
            'autoPrint' => ($version['source'] ?? '') !== 'amendment_draft',
        ], null);
    }

    public function preview()
    {
        $this->requireRole('admin');
        Auth::releaseSessionLock();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $principal = normalize_money($_GET['principal_amount'] ?? 0);
        $downPayment = normalize_money($_GET['down_payment_amount'] ?? 0);
        $months = (int) to_english_digits($_GET['months'] ?? 0);
        $rate = trim(str_replace(['٪', '%', 'درصد', ',', '،', '٬', ' '], '', to_english_digits((string) ($_GET['monthly_interest_rate'] ?? '0'))));
        $interestType = ($_GET['interest_type'] ?? 'compound') === 'simple' ? 'simple' : 'compound';
        if ($principal <= 0 || $downPayment > $principal || $months < 1 || $months > 480 || !preg_match('/^\d+(?:\.\d{1,4})?$/', $rate) || (float) $rate > 100) {
            $this->json(['ok' => false, 'message' => 'پارامترهای پیش‌نمایش قرارداد معتبر نیستند.'], 422);
        }
        $preview = FinanceHelper::contractPreview(
            $principal,
            $downPayment,
            $months,
            $rate,
            $interestType
        );
        $preview['formatted'] = [
            'principal_amount' => money_toman($preview['principal_amount']),
            'down_payment_amount' => money_toman($preview['down_payment_amount']),
            'financed_amount' => money_toman($preview['financed_amount']),
            'installment_amount' => money_toman($preview['installment_amount']),
            'total_payable' => money_toman($preview['total_payable']),
        ];
        $preview['calculation_version'] = 'contract-finance-v1';
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
        return $this->retire($id);
    }

    public function retire($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (empty($_POST['confirm_mistake']) && empty($_POST['confirm_delete_mistake'])) {
            set_flash('error', 'برای حذف قرارداد آزمایشی یا اشتباهی باید پیامدهای حذف را تأیید کنید.');
            redirect('contracts');
        }
        try {
            $result = Contract::deleteContractSafely(
                (int) $id,
                Auth::id(),
                $_POST['retirement_reason'] ?? ($_POST['deletion_reason'] ?? ''),
                $_POST['confirm_contract_number'] ?? '',
                !empty($_POST['include_related_history']) || !empty($_POST['purge_contract_history']),
                !empty($_POST['accept_gateway_notice']) || !empty($_POST['confirm_gateway_warning'])
            );
            set_flash(
                'success',
                !empty($result['history_purged'])
                    ? 'قرارداد و سوابق وابسته انتخاب‌شده پس از ثبت آرشیو کامل حذف شدند. هیچ بازگشت وجه بانکی انجام نشد.'
                    : 'قرارداد آزمایشی یا اشتباهی پس از ثبت آرشیو حذف شد.'
            );
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
            $result = Contract::cancel((int) $id, $_POST['cancellation_reason'] ?? '', Auth::id(), !empty($_POST['correct_contract_payments']));
            $message = !empty($result['already_cancelled'])
                ? 'این قرارداد قبلاً لغو شده است و نیازی به اجرای دوباره ندارد.'
                : 'قرارداد و اقساط فعال آن با موفقیت لغو شدند. سوابق مالی و تاریخی حفظ شده است.';
            if (!empty($result['corrected_payments'])) {
                $message .= ' تعداد ' . to_persian_digits($result['corrected_payments']) . ' پرداخت همین قرارداد نیز با اصلاحیه مالی صفر شد.';
            }
            set_flash('success', $message);
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
            'seller_user_id' => $this->resolveAttributionUserId($input['seller_user_id'] ?? ($contract['seller_user_id'] ?? ''), 'فروشنده', false),
            'marketer_user_id' => $this->resolveAttributionUserId($input['marketer_user_id'] ?? ($contract['marketer_user_id'] ?? ''), 'بازاریاب', true),
            'notes' => array_key_exists('notes', $input) ? $input['notes'] : ($contract['notes'] ?? ''),
            'updated_by' => Auth::id(),
            'change_reason' => trim((string) ($input['change_reason'] ?? '')) ?: 'ویرایش قرارداد',
        ];
    }

    protected function resolveAttributionUserId($value, string $label, bool $allowEmpty): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '' || (int) $raw <= 0) {
            if ($allowEmpty) {
                return null;
            }
            throw new InvalidArgumentException('انتخاب ' . $label . ' الزامی است.');
        }
        $userId = (int) $raw;
        $user = User::find($userId);
        if (!$user || ($user['status'] ?? '') !== 'active' || !in_array($user['role'] ?? '', ['admin', 'operator', 'lawyer', 'repair_technician'], true)) {
            throw new InvalidArgumentException($label . ' انتخاب‌شده معتبر یا فعال نیست.');
        }
        return $userId;
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
