<?php

class LegalController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'lawyer']);
        $filters = [
            'status' => in_array($_GET['status'] ?? '', ['open', 'referred', 'closed'], true) ? $_GET['status'] : null,
            'search' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'per_page' => 15,
        ];
        $filters['exclude_status'] = 'referred';
        if (Auth::role() === 'lawyer') {
            $filters['lawyer_id'] = (int) Auth::id();
        }
        $result = LegalCase::paginated($filters);
        $this->render('legal/index', [
            'title' => 'حقوقی و شکایت‌ها',
            'cases' => $result['items'],
            'pagination' => $result,
            'legalQueue' => 'main',
            'legalQueueRoute' => 'legal',
            'legalSummary' => $this->caseSummary(),
            'legalStageOptions' => LegalCaseLog::stageOptions(),
            'legalCostTypeOptions' => LegalCaseLog::costTypeOptions(),
        ], is_ajax_request() ? null : 'app');
    }

    public function referred()
    {
        $this->requireRole(['admin', 'lawyer']);
        $filters = ['status' => 'referred', 'search' => $_GET['q'] ?? null, 'page' => $_GET['page'] ?? 1, 'per_page' => 15];
        if (Auth::role() === 'lawyer') {
            $filters['lawyer_id'] = (int) Auth::id();
        }
        $result = LegalCase::paginated($filters);
        $this->render('legal/index', [
            'title' => 'پرونده‌های ارجاع‌شده', 'cases' => $result['items'], 'pagination' => $result,
            'legalQueue' => 'referred', 'legalQueueRoute' => 'legal/referred',
            'legalSummary' => $this->caseSummary(),
            'legalStageOptions' => LegalCaseLog::stageOptions(), 'legalCostTypeOptions' => LegalCaseLog::costTypeOptions(),
        ], is_ajax_request() ? null : 'app');
    }

    private function caseSummary(): array
    {
        $where = '';
        $params = [];
        if (Auth::role() === 'lawyer') {
            $where = ' WHERE lawyer_id = ?';
            $params[] = (int) Auth::id();
        }
        $row = Model::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status='open') AS open_count,
                    SUM(status='referred') AS referred_count,
                    SUM(status='closed') AS closed_count
             FROM legal_cases{$where}",
            $params
        ) ?: [];
        return array_map('intval', $row + ['total' => 0, 'open_count' => 0, 'referred_count' => 0, 'closed_count' => 0]);
    }

    public function export($queue = 'main')
    {
        $this->requireRole(['admin', 'lawyer']);
        $referred = $queue === 'referred';
        $filters = [
            'status' => $referred ? 'referred' : (in_array($_GET['status'] ?? '', ['open', 'referred', 'closed'], true) ? $_GET['status'] : null),
            'search' => trim((string) ($_GET['q'] ?? '')),
            'per_page' => 100,
        ];
        if (!$referred) $filters['exclude_status'] = 'referred';
        if (Auth::role() === 'lawyer') {
            $filters['lawyer_id'] = (int) Auth::id();
        }
        SpreadsheetHelper::downloadPaginatedXlsx(
            $referred ? 'پرونده‌های-ارجاع‌شده.xlsx' : 'پرونده‌های-حقوقی.xlsx',
            $referred ? 'پرونده‌های ارجاع‌شده' : 'پرونده‌های حقوقی',
            ['شماره قرارداد', 'مشتری', 'موبایل', 'وکیل', 'مرحله', 'شماره شکایت', 'هزینه ثبت‌شده (تومان)', 'وضعیت', 'آخرین تغییر'],
            static function (int $page, int $perPage) use ($filters): array {
                $pageFilters = $filters;
                $pageFilters['page'] = $page;
                $pageFilters['per_page'] = min(100, $perPage);
                return LegalCase::paginated($pageFilters);
            },
            static fn (array $row): array => [
                (string) ($row['contract_number'] ?? ''), (string) ($row['customer_name'] ?? ''),
                (string) ($row['mobile'] ?? ''), (string) ($row['lawyer_name'] ?? ''),
                (string) ($row['stage'] ?? ''), (string) ($row['complaint_number'] ?? ''),
                normalize_money($row['expense_amount'] ?? 0), status_label($row['status'] ?? ''),
                (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            ],
            100
        );
    }

    public function eligible()
    {
        $this->requireRole(['admin', 'lawyer']);
        $contracts = LegalCase::eligibleContracts(trim((string) ($_GET['q'] ?? '')));
        $existingCases = Model::fetchAll("SELECT contract_id FROM legal_cases WHERE status NOT IN ('closed', 'archived')");
        $activeContractIds = array_fill_keys(array_map(static fn ($row) => (int) $row['contract_id'], $existingCases), true);
        $contracts = array_values(array_filter($contracts, static fn ($contract) => empty($activeContractIds[(int) ($contract['id'] ?? 0)])));
        $this->render('legal/eligible', ['title' => 'قراردادهای واجد شرایط بررسی حقوقی', 'contracts' => $contracts, 'search' => trim((string) ($_GET['q'] ?? ''))]);
    }

    public function create()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalCase::createCase($_POST['lawyer_id'] ?? null, (int) $_POST['contract_id'], $_POST['notes'] ?? '', $_POST['stage'] ?? 'ثبت اولیه');
            set_flash('success', 'پرونده حقوقی ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت پرونده انجام نشد.');
        }
        redirect('legal/referred');
    }

    public function costs()
    {
        $this->requireRole(['admin', 'lawyer']);
        $isAdmin = Auth::role() === 'admin';
        $status = in_array($_GET['status'] ?? '', ['pending_approval', 'approved', 'reversed'], true) ? $_GET['status'] : 'pending_approval';
        $companyPayment = $isAdmin && in_array($_GET['company_payment'] ?? '', ['unpaid', 'partial', 'paid'], true)
            ? (string) $_GET['company_payment']
            : 'all';
        if ($companyPayment !== 'all') $status = 'approved';
        $thirdPartyLedgerReady = LegalCaseCostService::disbursementTableAvailable();
        $search = trim((string) ($_GET['q'] ?? ''));
        $params = [$status];
        $where = "WHERE c.approval_status = ?";
        if ($companyPayment !== 'all') {
            if (!$thirdPartyLedgerReady) {
                $where .= ' AND 1 = 0';
            } else {
                $companyPaidSql = '(SELECT COALESCE(SUM(d.amount_toman), 0) FROM legal_cost_disbursements d WHERE d.legal_case_cost_id = c.id)';
                $where .= " AND c.approval_status = 'approved' AND c.reversed_at IS NULL AND c.reimburse_lawyer_id IS NULL AND c.amount_toman > 0";
                if ($companyPayment === 'unpaid') $where .= " AND {$companyPaidSql} <= 0";
                if ($companyPayment === 'partial') $where .= " AND {$companyPaidSql} > 0 AND {$companyPaidSql} < c.amount_toman";
                if ($companyPayment === 'paid') $where .= " AND {$companyPaidSql} >= c.amount_toman";
            }
        }
        if (!$isAdmin) {
            $where .= ' AND lc.lawyer_id = ?';
            $params[] = (int) Auth::id();
        }
        if ($search !== '') {
            $where .= " AND (co.contract_number LIKE ? OR cu.full_name LIKE ? OR c.title LIKE ? OR c.category LIKE ?)";
            $needle = '%' . $search . '%';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        $orderBy = $companyPayment === 'all' ? 'c.cost_date DESC, c.id DESC' : 'c.cost_date ASC, c.id ASC';
        $costs = LegalCaseCostService::tableAvailable() ? Model::fetchAll(
            "SELECT c.*, co.contract_number, cu.full_name AS customer_name, u.full_name AS created_by_name, a.full_name AS approved_by_name,
                    reimbursement_lawyer.full_name AS reimbursement_lawyer_name,
                    lc.lawyer_id AS assigned_lawyer_id, case_lawyer.full_name AS assigned_lawyer_name, lc.legal_referred_at
             FROM legal_case_costs c
             LEFT JOIN contracts co ON co.id = c.contract_id
             LEFT JOIN users cu ON cu.id = co.customer_id
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN users a ON a.id = c.approved_by
             LEFT JOIN users reimbursement_lawyer ON reimbursement_lawyer.id = c.reimburse_lawyer_id
             LEFT JOIN legal_cases lc ON lc.id = c.legal_case_id AND lc.contract_id = c.contract_id
             LEFT JOIN users case_lawyer ON case_lawyer.id = lc.lawyer_id
             {$where}
             ORDER BY {$orderBy}
             LIMIT 200",
            $params
        ) : [];
        $thirdPartyByCost = LegalCaseCostService::thirdPartyPaymentsByCost(array_column($costs, 'id'));
        foreach ($costs as &$cost) {
            $ledger = $thirdPartyByCost[(int) $cost['id']] ?? ['total' => 0, 'items' => []];
            $cost['company_paid_amount_toman'] = normalize_money($ledger['total'] ?? 0);
            $cost['third_party_payments'] = $ledger['items'] ?? [];
            $companyCostAmount = normalize_money($cost['amount_toman'] ?? 0);
            $companyRemaining = max(0, $companyCostAmount - max(0, $cost['company_paid_amount_toman']));
            $cost['company_payment_remaining_toman'] = $companyRemaining;
            $cost['company_payment_status'] = ($cost['approval_status'] ?? '') !== 'approved'
                ? 'awaiting_approval'
                : (!empty($cost['reimburse_lawyer_id'])
                    ? 'lawyer_reimbursement'
                    : ($companyCostAmount <= 0 ? 'not_payable' : ($companyRemaining <= 0 ? 'paid' : ($cost['company_paid_amount_toman'] > 0 ? 'partial' : 'unpaid'))));
            $cost['can_pay_to_authority'] = $thirdPartyLedgerReady
                && ($cost['approval_status'] ?? '') === 'approved'
                && empty($cost['reversed_at'])
                && empty($cost['reimburse_lawyer_id'])
                && $companyRemaining > 0;
            $canCharge = ($cost['approval_status'] ?? '') === 'approved'
                && (int) ($cost['chargeable_to_customer'] ?? 0) === 1
                && empty($cost['reversed_at'])
                && !in_array((string) ($cost['payment_status'] ?? ''), ['reimbursed', 'cancelled'], true)
                && !empty($cost['legal_referred_at']);
            $cost['customer_receivable_toman'] = $canCharge
                ? max(0, normalize_money($cost['amount_toman'] ?? 0) - normalize_money($cost['paid_amount_toman'] ?? 0))
                : 0;
        }
        unset($cost);
        $this->render('legal/costs', [
            'title' => $isAdmin ? ($companyPayment === 'all' ? 'مدیریت تأیید و پرداخت هزینه‌های حقوقی' : 'پرداخت هزینه‌های حقوقی به مرجع') : 'هزینه‌های حقوقی پرونده‌های من',
            'costs' => $costs,
            'status' => $status,
            'companyPayment' => $companyPayment,
            'search' => $search,
            'registryReady' => LegalCaseCostService::tableAvailable(),
            'thirdPartyLedgerReady' => $thirdPartyLedgerReady,
            'canManageCosts' => $isAdmin,
        ], is_ajax_request() ? null : 'app');
    }

    public function approveCost($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalCaseCostService::approve((int) $id, (int) Auth::id());
            set_flash('success', 'هزینه حقوقی تأیید شد و در محاسبات قابل مطالبه وارد شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تأیید هزینه حقوقی انجام نشد.');
        }
        redirect('legal/costs');
    }

    public function reverseCost($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalCaseCostService::reverse((int) $id, (string) ($_POST['reason'] ?? ''), (int) Auth::id());
            set_flash('success', 'هزینه حقوقی با حفظ تاریخچه برگشت داده شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'برگشت هزینه حقوقی انجام نشد.');
        }
        redirect('legal/costs');
    }

    public function recordThirdPartyCostPayment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $date = parse_jalali_date((string) ($_POST['paid_at'] ?? '')) ?: trim((string) ($_POST['paid_at'] ?? ''));
            LegalCaseCostService::recordThirdPartyPayment(
                (int) $id,
                normalize_money($_POST['amount_toman'] ?? 0),
                (string) ($_POST['recipient'] ?? ''),
                (string) ($_POST['payment_reference'] ?? ''),
                $date,
                (string) ($_POST['note'] ?? ''),
                (string) ($_POST['idempotency_key'] ?? ''),
                (int) Auth::id()
            );
            set_flash('success', 'پرداخت مجموعه به مرجع ثالث ثبت شد؛ مانده قابل وصول از مشتری تغییر نکرد.');
        } catch (Throwable $e) {
            if (!$e instanceof InvalidArgumentException && class_exists('PluginRegistry')) {
                PluginRegistry::logRuntimeError('legal_cost.third_party_payment', $e);
            }
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'ثبت پرداخت به مرجع ثالث انجام نشد.');
        }
        redirect('legal/costs?status=approved');
    }

    public function reverseThirdPartyCostPayment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalCaseCostService::reverseThirdPartyPayment((int) $id, (string) ($_POST['reason'] ?? ''), (int) Auth::id());
            set_flash('success', 'برگشت پرداخت به مرجع ثالث ثبت شد؛ سابقه پرداخت حفظ شد و بدهی مشتری تغییر نکرد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'برگشت پرداخت به مرجع ثالث انجام نشد.');
        }
        redirect('legal/costs?status=approved');
    }

    public function lawyerPayments()
    {
        $this->requireRole(['admin', 'lawyer']);
        $isAdmin = Auth::role() === 'admin';
        $ready = LegalLawyerSettlementService::ready();
        $summaries = $ready ? LegalLawyerSettlementService::lawyerSummaries() : [];
        $lawyerId = $isAdmin ? (int) ($_GET['lawyer_id'] ?? 0) : (int) Auth::id();
        if (!$isAdmin) {
            $summaries = array_values(array_filter($summaries, static fn (array $row): bool => (int) $row['lawyer_id'] === $lawyerId));
        }
        $selectedSummary = null;
        foreach ($summaries as $summary) {
            if ((int) $summary['lawyer_id'] === $lawyerId) {
                $selectedSummary = $summary;
                break;
            }
        }
        if ($isAdmin && !$selectedSummary && $summaries) {
            $selectedSummary = $summaries[0];
            $lawyerId = (int) $selectedSummary['lawyer_id'];
        }
        $this->render('legal/lawyer-payments', [
            'title' => $isAdmin ? 'تسویه حساب وکلا' : 'صورت‌حساب من',
            'isAdmin' => $isAdmin,
            'registryReady' => $ready,
            'lawyerSummaries' => $summaries,
            'selectedLawyerId' => $lawyerId,
            'selectedSummary' => $selectedSummary,
            'outstandingCosts' => $ready && $selectedSummary ? LegalLawyerSettlementService::outstandingCosts($lawyerId) : [],
            'paymentBatches' => $ready && $selectedSummary ? LegalLawyerSettlementService::paymentBatches($lawyerId) : [],
            'adjustments' => $ready && $selectedSummary ? LegalLawyerSettlementService::adjustments($lawyerId) : [],
        ], is_ajax_request() ? null : 'app');
    }

    public function recordLawyerPayment()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $costIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['cost_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
            $amounts = (array) ($_POST['amounts'] ?? []);
            $allocations = [];
            foreach ($costIds as $costId) {
                $allocations[] = ['cost_id' => $costId, 'amount_toman' => normalize_money($amounts[$costId] ?? 0)];
            }
            $paidAt = parse_jalali_date((string) ($_POST['paid_at'] ?? '')) ?: trim((string) ($_POST['paid_at'] ?? ''));
            $row = LegalLawyerSettlementService::recordPayment(
                (int) ($_POST['lawyer_id'] ?? 0), $allocations, $paidAt,
                (string) ($_POST['payment_reference'] ?? ''), (string) ($_POST['note'] ?? ''),
                (string) ($_POST['idempotency_key'] ?? ''), (int) Auth::id()
            );
            set_flash('success', 'پرداخت وکیل ثبت شد و به هزینه‌های انتخاب‌شده تخصیص یافت.');
            redirect('legal/lawyerPayments?lawyer_id=' . (int) ($row['lawyer_id'] ?? $_POST['lawyer_id'] ?? 0));
            return;
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'ثبت تسویه وکیل انجام نشد.');
        }
        redirect('legal/lawyerPayments?lawyer_id=' . (int) ($_POST['lawyer_id'] ?? 0));
    }

    public function reverseLawyerPayment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $lawyerId = (int) ($_POST['lawyer_id'] ?? 0);
        try {
            LegalLawyerSettlementService::reversePayment((int) $id, (string) ($_POST['reason'] ?? ''), (int) Auth::id());
            set_flash('success', 'برگشت پرداخت وکیل ثبت شد و مانده قابل تسویه دوباره محاسبه می‌شود.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'برگشت پرداخت وکیل انجام نشد.');
        }
        redirect('legal/lawyerPayments?lawyer_id=' . $lawyerId);
    }

    public function markLawyerReimbursement($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalLawyerSettlementService::markCostForReimbursement((int) $id, (int) Auth::id());
            set_flash('success', 'هزینهٔ تأییدشده در صورت‌حساب وکیل پرونده ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'ثبت هزینه در حساب وکیل انجام نشد.');
        }
        redirect('legal/costs?status=approved');
    }

    public function addLawyerAdjustment()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $lawyerId = (int) ($_POST['lawyer_id'] ?? 0);
        try {
            $effectiveAt = parse_jalali_date((string) ($_POST['effective_at'] ?? '')) ?: trim((string) ($_POST['effective_at'] ?? ''));
            LegalLawyerSettlementService::addAdjustment(
                $lawyerId, (string) ($_POST['entry_type'] ?? ''), normalize_money($_POST['amount_toman'] ?? 0),
                $effectiveAt, (string) ($_POST['description'] ?? ''), (string) ($_POST['idempotency_key'] ?? ''), (int) Auth::id()
            );
            set_flash('success', 'رویداد بستانکاری/بدهکاری وکیل در دفتر حساب ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'ثبت رویداد حساب وکیل انجام نشد.');
        }
        redirect('legal/lawyerPayments?lawyer_id=' . $lawyerId);
    }

    public function reverseLawyerAdjustment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $lawyerId = (int) ($_POST['lawyer_id'] ?? 0);
        try {
            LegalLawyerSettlementService::reverseAdjustment((int) $id, (string) ($_POST['reason'] ?? ''), (int) Auth::id());
            set_flash('success', 'اصلاحیه برگشت ثبت شد و مانده وکیل دوباره محاسبه می‌شود.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'برگشت رویداد حساب وکیل انجام نشد.');
        }
        redirect('legal/lawyerPayments?lawyer_id=' . $lawyerId);
    }

    public function show($id)
    {
        Auth::requireLogin();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }

        $contract = Contract::find((int) $case['contract_id']);
        // One bounded load is shared by the legal workspace, payment quote and
        // contract summary.  The old page recalculated every row with an
        // independent payment query, which could show a stale zero and create
        // an N+1 pattern on a shared host.
        $batch = ContractInstallmentFinancialBatchService::load((int) $case['contract_id']);
        $installments = $batch['items'];
        $legalCostSummary = ContractLegalCostSummaryService::forContract((int) $case['contract_id']);
        $legalCosts = LegalCaseCostService::forContract((int) $case['contract_id']);
        $disbursementsByCost = LegalCaseCostService::thirdPartyPaymentsByCost(array_column($legalCosts, 'id'));
        foreach ($legalCosts as &$legalCost) {
            $legalCost['company_paid_amount_toman'] = normalize_money($disbursementsByCost[(int) $legalCost['id']]['total'] ?? 0);
            $chargeable = ($legalCost['approval_status'] ?? '') === 'approved'
                && (int) ($legalCost['chargeable_to_customer'] ?? 0) === 1
                && empty($legalCost['reversed_at'])
                && !in_array((string) ($legalCost['payment_status'] ?? ''), ['reimbursed', 'cancelled'], true)
                && !empty($case['legal_referred_at']);
            $legalCost['customer_receivable_toman'] = $chargeable
                ? max(0, normalize_money($legalCost['amount_toman'] ?? 0) - normalize_money($legalCost['paid_amount_toman'] ?? 0))
                : 0;
        }
        unset($legalCost);
        $financialSummary = ContractFinancialSummaryService::summarize((int) $case['contract_id'], null, $installments);
        $legalFinancials = $this->buildLegalFinancials($installments, $financialSummary, $legalCostSummary);

        $this->render('legal/show', [
            'title' => 'جزئیات پرونده حقوقی',
            'case' => $case,
            'contract' => $contract,
            'installments' => $installments,
            'legalFinancials' => $legalFinancials,
            'financialSummary' => $financialSummary,
            'legalCostSummary' => $legalCostSummary,
            'legalCosts' => $legalCosts,
            'financialBatchWarnings' => $batch['warnings'],
            'timeline' => LegalCase::timeline((int) $id),
            'payments' => LegalCase::paymentsForCase((int) $id),
            'legalDocuments' => LegalDocumentService::forCase((int) $id),
            'eligibility' => LegalEligibilityService::forContract((int) $case['contract_id']),
            'overdueCount' => LegalCase::overdueCountForCase((int) $id),
            'canViewCosts' => in_array(Auth::role(), ['admin', 'lawyer'], true),
            'canAttachLegalFile' => in_array(Auth::role(), ['admin', 'lawyer'], true),
            'canRegisterLegalCost' => in_array(Auth::role(), ['admin', 'lawyer'], true),
            'legalCostTypeOptions' => LegalCaseLog::costTypeOptions(),
            'canDeleteCase' => Auth::role() === 'admin',
            'legalStageOptions' => LegalCaseLog::stageOptions(),
            'canOpenContract' => Auth::role() === 'admin'
                || (Auth::role() === 'lawyer' && (int) ($case['lawyer_id'] ?? 0) === (int) Auth::id()),
        ]);
    }

    public function storeAttachment($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        try {
            if (empty($_FILES['attachment']['tmp_name'])) {
                throw new InvalidArgumentException('فایل ضمیمه را انتخاب کنید.');
            }
            LegalCaseLog::createLog([
                'contract_id' => (int) $case['contract_id'],
                'legal_case_id' => (int) $case['id'],
                'action_stage' => $_POST['action_stage'] ?? ($case['stage'] ?? 'سایر'),
                'action_title' => trim((string) ($_POST['action_title'] ?? '')) ?: 'ارسال فایل ضمیمه پرونده',
                'description' => $_POST['description'] ?? '',
                'action_date' => $_POST['action_date'] ?? date('Y-m-d'),
                'action_time' => $_POST['action_time'] ?? date('H:i'),
                'registered_by' => Auth::id(),
                'assigned_lawyer_id' => $case['lawyer_id'] ?? null,
                'next_status' => $case['status'] ?? 'referred',
                'cost_amount' => 0,
                'cost_type' => '',
            ], $_FILES['attachment'] ?? []);
            set_flash('success', 'فایل ضمیمه پرونده ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت فایل ضمیمه انجام نشد.');
        }
        redirect('legal/show/' . (int) $id);
    }

    public function storeCost($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
            ErrorHandler::abort(403);
        }
        try {
            $amount = normalize_money($_POST['cost_amount'] ?? 0);
            $type = trim((string) ($_POST['cost_type'] ?? ''));
            if ($amount <= 0 || $type === '') {
                throw new InvalidArgumentException('مبلغ و نوع هزینه حقوقی الزامی است.');
            }
            LegalCaseLog::createLog([
                'contract_id' => (int) $case['contract_id'],
                'legal_case_id' => (int) $case['id'],
                'action_stage' => $_POST['action_stage'] ?? ($case['stage'] ?? 'سایر'),
                'action_title' => trim((string) ($_POST['action_title'] ?? '')) ?: 'ثبت هزینه حقوقی',
                'description' => $_POST['description'] ?? '',
                'action_date' => $_POST['action_date'] ?? date('Y-m-d'),
                'action_time' => $_POST['action_time'] ?? date('H:i'),
                'registered_by' => Auth::id(),
                'assigned_lawyer_id' => $case['lawyer_id'] ?? (Auth::role() === 'lawyer' ? Auth::id() : null),
                'next_status' => $case['status'] ?? 'under_legal_review',
                'cost_amount' => $amount,
                'cost_type' => $type,
                'reimburse_lawyer' => !empty($_POST['reimburse_lawyer']),
            ], $_FILES['attachment'] ?? []);
            set_flash('success', 'هزینه حقوقی ثبت و برای تأیید مدیریت ارسال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت هزینه حقوقی انجام نشد.');
        }
        redirect('legal/show/' . (int) $id);
    }

    public function storeProgress($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
            ErrorHandler::abort(403);
        }
        try {
            LegalCaseLog::createLog([
                'contract_id' => (int) $case['contract_id'],
                'legal_case_id' => (int) $case['id'],
                'action_stage' => $_POST['action_stage'] ?? ($case['stage'] ?? 'سایر'),
                'action_title' => trim((string) ($_POST['action_title'] ?? '')) ?: 'ثبت روند پرونده',
                'description' => $_POST['description'] ?? '',
                'action_date' => $_POST['action_date'] ?? date('Y-m-d'),
                'action_time' => $_POST['action_time'] ?? date('H:i'),
                'registered_by' => Auth::id(),
                'assigned_lawyer_id' => $case['lawyer_id'] ?? null,
                'next_status' => $_POST['next_status'] ?? ($case['status'] ?? 'referred'),
                'cost_amount' => 0,
                'cost_type' => '',
            ]);
            set_flash('success', 'روند/مرحله پرونده حقوقی ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت روند پرونده انجام نشد.');
        }
        redirect(Auth::role() === 'admin' ? 'legal' : 'legal/show/' . (int) $id);
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalCase::updateCase((int) $id, $_POST);
            set_flash('success', 'پرونده حقوقی به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'به‌روزرسانی پرونده انجام نشد.');
        }
        redirect('legal');
    }

    public function createDocument($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
            ErrorHandler::abort(403);
        }
        try {
            LegalDocumentService::createInternal($case, $_POST['document_type'] ?? '', Auth::id(), $_POST);
            set_flash('success', 'سند داخلی حقوقی ثبت شد. این سند ادعای ثبت یا ابلاغ رسمی ندارد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت سند داخلی انجام نشد.');
        }
        redirect('legal/show/' . (int) $id);
    }

    public function confirmExternalDocument($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        $document = LegalDocumentService::find((int) $id);
        $case = $document ? LegalCase::find((int) $document['legal_case_id']) : null;
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
            ErrorHandler::abort(403);
        }
        try {
            LegalDocumentService::confirmExternal((int) $id, Auth::id(), $_POST);
            set_flash('success', 'تایید ثبت بیرونی همراه با مرجع و شماره پیگیری واقعی ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تایید ثبت بیرونی انجام نشد.');
        }
        redirect('legal/show/' . (int) $case['id']);
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('legal_case_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف پرونده حقوقی درست وارد نشده است.');
            }
            LegalCase::deleteCase((int) $id);
            set_flash('success', 'پرونده حقوقی بایگانی شد؛ تاریخچه و مستندات آن حفظ شده‌اند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'حذف پرونده حقوقی انجام نشد.');
        }
        redirect('legal');
    }

    protected function buildLegalFinancials(array $installments, array $financialSummary, array $legalCostSummary)
    {
        $rows = [];
        $totals = [
            'base_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'normal_penalty' => 0,
            'legal_penalty' => 0,
            'projected_legal_penalty' => 0,
            'effective_penalty_payable' => 0,
            'normal_payable' => 0,
            'legal_payable' => 0,
        ];
        foreach ($installments as $installment) {
            // ContractInstallmentFinancialBatchService has already attached
            // the canonical state.  Never reproduce a second financial model
            // inside the legal screen.
            $remainingToday = normalize_money($installment['remaining_principal'] ?? $installment['remaining_amount'] ?? 0);
            $normalPenalty = normalize_money($installment['normal_penalty'] ?? 0);
            $legalPenalty = normalize_money($installment['legal_penalty'] ?? 0);
            $reward = normalize_money($installment['eligible_reward'] ?? 0);
            $row = $installment;
            $row['normal_penalty_amount'] = $remainingToday > 0 ? $normalPenalty : 0;
            $row['legal_penalty_amount'] = $remainingToday > 0 ? $legalPenalty : 0;
            $row['projected_legal_penalty_amount'] = $remainingToday > 0 ? normalize_money($installment['projected_legal_penalty'] ?? 0) : 0;
            $row['effective_penalty_payable_amount'] = $remainingToday > 0 ? normalize_money($installment['effective_penalty_payable'] ?? 0) : 0;
            $row['normal_payable_amount'] = $remainingToday > 0 ? max(0, $remainingToday + $normalPenalty - $reward) : 0;
            $row['legal_payable_amount'] = $remainingToday > 0 ? normalize_money($installment['final_payable'] ?? 0) : 0;
            $row['remaining_today'] = $remainingToday;
            $row['grace_days'] = (int) ($installment['grace_days'] ?? 0);
            $rows[] = $row;

            $totals['base_amount'] += normalize_money($installment['base_amount'] ?? 0);
            $totals['paid_amount'] += normalize_money($installment['effective_paid_principal'] ?? $installment['paid_amount'] ?? 0);
            $totals['remaining_amount'] += normalize_money($row['remaining_today'] ?? 0);
            $totals['normal_penalty'] += $row['normal_penalty_amount'];
            $totals['legal_penalty'] += $row['legal_penalty_amount'];
            $totals['projected_legal_penalty'] += $row['projected_legal_penalty_amount'];
            $totals['effective_penalty_payable'] += $row['effective_penalty_payable_amount'];
            $totals['normal_payable'] += $row['normal_payable_amount'];
            $totals['legal_payable'] += $row['legal_payable_amount'];
        }
        $totals['pending_approval_legal_costs'] = normalize_money($legalCostSummary['pending_approval_legal_costs'] ?? 0);
        $totals['approved_legal_costs'] = normalize_money($legalCostSummary['approved_legal_costs'] ?? 0);
        $totals['collected_legal_costs'] = normalize_money($legalCostSummary['collected_legal_costs'] ?? 0);
        $totals['company_paid_legal_costs'] = normalize_money($legalCostSummary['company_paid_legal_costs'] ?? 0);
        $totals['complaint_costs'] = normalize_money($legalCostSummary['outstanding_chargeable_legal_costs'] ?? 0);
        $totals['normal_collectable'] = $totals['normal_payable'];
        $totals['legal_collectable'] = normalize_money($financialSummary['final_collectable_amount'] ?? 0);
        $totals['calculation_status'] = (string) ($financialSummary['calculation_status'] ?? 'calculation_failed');
        $totals['calculation_warnings'] = (array) ($financialSummary['calculation_warnings'] ?? []);

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
