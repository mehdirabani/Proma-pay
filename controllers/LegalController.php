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
        $installments = Installment::all(['contract_id' => (int) $case['contract_id']]);

        $this->render('legal/show', [
            'title' => 'جزئیات پرونده حقوقی',
            'case' => $case,
            'contract' => $contract,
            'installments' => $installments,
            'timeline' => LegalCase::timeline((int) $id),
            'payments' => LegalCase::paymentsForCase((int) $id),
            'overdueCount' => LegalCase::overdueCountForCase((int) $id),
            'canViewCosts' => in_array(Auth::role(), ['admin', 'lawyer'], true),
            'canAttachLegalFile' => in_array(Auth::role(), ['admin', 'lawyer'], true),
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
}
