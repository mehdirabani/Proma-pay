<?php

class LawyerController extends Controller
{
    public function index()
    {
        $this->requireRole('lawyer');
        $params = array_filter([
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : '',
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
        ], static fn ($value) => $value !== '' && $value !== 1);
        redirect('legal', $params);
    }

    public function export()
    {
        $this->requireRole('lawyer');
        redirect('legal/export', array_filter([
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : '',
        ], static fn ($value) => $value !== ''));
    }

    public function create()
    {
        $this->requireRole('lawyer');
        $this->onlyPost();
        try {
            $caseId = LegalCase::createSelfInitiated(Auth::id(), (int) ($_POST['contract_id'] ?? 0), $_POST['notes'] ?? '', $_POST['legal_case_request_uuid'] ?? '');
            set_flash('success', 'پرونده داخلی حقوقی ایجاد شد. این ثبت، ثبت رسمی قضایی یا ابلاغ رسمی نیست.');
            redirect('legal/show/' . $caseId);
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ایجاد پرونده داخلی انجام نشد.');
        }
        redirect('lawyer');
    }

    public function update($id)
    {
        $this->requireRole('lawyer');
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || !LegalCase::canAccess($case, Auth::user())) {
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
        redirect('legal/show/' . (int) $id);
    }
}
