<?php

class LegalController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filters = [
            'status' => in_array($_GET['status'] ?? '', ['open', 'referred', 'closed'], true) ? $_GET['status'] : null,
            'search' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'per_page' => 30,
        ];
        $result = LegalCase::paginated($filters + ['eligible_only' => true]);
        $this->render('legal/index', [
            'title' => 'حقوقی و شکایت‌ها',
            'cases' => $result['items'],
            'pagination' => $result,
        ], is_ajax_request() ? null : 'app');
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
        redirect('legal');
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
            'legalCosts' => LegalCaseCostService::forContract((int) $case['contract_id']),
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
                'assigned_lawyer_id' => $case['lawyer_id'] ?? Auth::id(),
                'next_status' => $case['status'] ?? 'under_legal_review',
                'cost_amount' => $amount,
                'cost_type' => $type,
            ], $_FILES['attachment'] ?? []);
            set_flash('success', 'هزینه حقوقی ثبت و برای تأیید مدیریت ارسال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت هزینه حقوقی انجام نشد.');
        }
        redirect('legal/show/' . (int) $id);
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
