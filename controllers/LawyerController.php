<?php

class LawyerController extends Controller
{
    public function index()
    {
        $this->requireRole('lawyer');
        $this->render('lawyer/index', [
            'title' => 'پنل حقوقی',
            'cases' => LegalCase::all(['lawyer_id' => Auth::id()]),
            'eligible' => LegalCase::eligibleContracts(),
        ]);
    }

    public function create()
    {
        $this->requireRole('lawyer');
        $this->onlyPost();
        LegalCase::createCase(Auth::id(), (int) $_POST['contract_id'], $_POST['notes'] ?? '');
        set_flash('success', 'پرونده شکایت ثبت شد.');
        redirect('lawyer');
    }

    public function update($id)
    {
        $this->requireRole('lawyer');
        $this->onlyPost();
        $case = LegalCase::find((int) $id);
        if (!$case || ($case['lawyer_id'] && (int) $case['lawyer_id'] !== (int) Auth::id())) {
            set_flash('error', 'دسترسی به این پرونده مجاز نیست.');
            redirect('lawyer');
        }
        $_POST['lawyer_id'] = Auth::id();
        LegalCase::updateCase((int) $id, $_POST);
        set_flash('success', 'وضعیت پرونده به‌روزرسانی شد.');
        redirect('lawyer');
    }
}
