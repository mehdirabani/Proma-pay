<?php

class OperatorController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('operator/index', [
            'title' => 'پنل اپراتور',
            'contracts' => Contract::all(['operator_id' => Auth::id()]),
            'calls' => OperatorCall::all(Auth::id()),
        ]);
    }

    public function call()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $next = parse_jalali_date($_POST['next_followup_date'] ?? '');
        OperatorCall::createCall(Auth::id(), (int) $_POST['contract_id'], $_POST['call_result'] ?? '', $_POST['notes'] ?? '', $next);
        set_flash('success', 'گزارش تماس ثبت شد.');
        redirect('operator');
    }

    public function referLegal()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            LegalCase::createCase(null, (int) $_POST['contract_id'], $_POST['notes'] ?? '', $_POST['reason'] ?? 'ارجاع اپراتور');
            set_flash('success', 'پرونده برای بررسی حقوقی ارسال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ارجاع حقوقی انجام نشد.');
        }
        redirect('operator');
    }
}
