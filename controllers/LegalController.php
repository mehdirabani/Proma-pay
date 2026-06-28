<?php

class LegalController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filters = [
            'status' => in_array($_GET['status'] ?? '', ['open', 'referred', 'closed'], true) ? $_GET['status'] : null,
            'search' => $_GET['q'] ?? null,
        ];
        $this->render('legal/index', [
            'title' => 'حقوقی و شکایت‌ها',
            'cases' => LegalCase::all($filters),
            'eligible' => LegalCase::eligibleContracts(),
            'lawyers' => User::all('lawyer'),
        ]);
    }

    public function create()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        LegalCase::createCase($_POST['lawyer_id'] ?? null, (int) $_POST['contract_id'], $_POST['notes'] ?? '');
        set_flash('success', 'پرونده حقوقی ثبت شد.');
        redirect('legal');
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        LegalCase::updateCase((int) $id, $_POST);
        set_flash('success', 'پرونده حقوقی به‌روزرسانی شد.');
        redirect('legal');
    }
}
