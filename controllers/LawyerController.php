<?php

class LawyerController extends Controller
{
    public function index()
    {
        $this->requireRole('lawyer');
        $filters = [
            'lawyer_id' => Auth::id(),
            'search' => $_GET['q'] ?? null,
            'status' => in_array($_GET['status'] ?? '', ['open', 'referred', 'closed'], true) ? $_GET['status'] : null,
            'sort' => $_GET['sort'] ?? 'created',
            'dir' => $_GET['dir'] ?? 'desc',
            'limit' => 50,
        ];
        $cases = LegalCase::all(array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        }));
        $this->render('lawyer/index', [
            'title' => 'پنل حقوقی',
            'cases' => $cases,
            'readyCases' => [],
            'filedCases' => array_values(array_filter($cases, function ($case) {
                return ($case['status'] ?? '') !== 'referred';
            })),
            'referredCases' => array_values(array_filter($cases, function ($case) {
                return ($case['status'] ?? '') === 'referred';
            })),
            'eligible' => [],
        ], is_ajax_request() ? null : 'app');
    }

    public function export()
    {
        $this->requireRole('lawyer');
        $cases = LegalCase::all([
            'lawyer_id' => Auth::id(),
            'search' => $_GET['q'] ?? null,
            'status' => in_array($_GET['status'] ?? '', ['open', 'referred', 'closed'], true) ? $_GET['status'] : null,
            'sort' => $_GET['sort'] ?? 'created',
            'dir' => $_GET['dir'] ?? 'desc',
        ]);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="lawyer-cases.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['contract', 'customer', 'mobile', 'stage', 'complaint_number', 'status', 'created_at']);
        foreach ($cases as $case) {
            fputcsv($out, [
                $case['contract_number'],
                $case['customer_name'],
                $case['mobile'],
                $case['stage'],
                $case['complaint_number'],
                $case['status'],
                $case['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function create()
    {
        $this->requireRole('lawyer');
        $this->onlyPost();
        set_flash('error', 'ثبت پرونده حقوقی فقط پس از ارجاع مدیریت و در محدوده پرونده‌های اختصاص‌یافته مجاز است.');
        redirect('lawyer');
    }

    public function update($id)
    {
        $this->requireRole('lawyer');
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::contractIsEligible((int) $case['contract_id']) || ($case['lawyer_id'] && (int) $case['lawyer_id'] !== (int) Auth::id())) {
            set_flash('error', 'دسترسی به این پرونده مجاز نیست.');
            redirect('lawyer');
        }
        $_POST['lawyer_id'] = Auth::id();
        try {
            LegalCase::updateCase((int) $id, $_POST);
            set_flash('success', 'وضعیت پرونده به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'به‌روزرسانی پرونده انجام نشد.');
        }
        redirect('lawyer');
    }
}
