<?php

class OverdueController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $this->render('overdue/index', [
            'title' => 'اقساط سررسید گذشته',
            'bucket' => $_GET['bucket'] ?? null,
            'installments' => Installment::overdue($_GET['bucket'] ?? null),
            'operators' => User::all('operator'),
            'lawyers' => User::all('lawyer'),
        ]);
    }

    public function discount($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        Installment::discountPenalty((int) $id, $_POST['discount_type'] ?? 'fixed', $_POST['discount_value'] ?? 0, Auth::id());
        set_flash('success', 'تخفیف جریمه ثبت شد.');
        redirect('overdue');
    }

    public function assignOperator($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $installment = Installment::find((int) $id);
        if ($installment) {
            Model::execute('UPDATE contracts SET assigned_operator_id = ? WHERE id = ?', [(int) $_POST['operator_id'], $installment['contract_id']]);
            Notification::create((int) $_POST['operator_id'], 'قسط سررسید شده', 'یک قرارداد برای پیگیری به شما ارجاع شد.', 'overdue', url('operator'));
            set_flash('success', 'قرارداد به اپراتور ارجاع شد.');
        }
        redirect('overdue');
    }

    public function sendLawyer($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        $installment = Installment::find((int) $id);
        if ($installment) {
            LegalCase::createCase($_POST['lawyer_id'] ?: null, $installment['contract_id'], $_POST['notes'] ?? '');
            set_flash('success', 'پرونده برای واحد حقوقی ثبت شد.');
        }
        redirect('overdue');
    }
}
