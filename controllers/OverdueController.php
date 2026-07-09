<?php

class OverdueController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $this->render('overdue/index', [
            'title' => 'اقساط سررسید گذشته',
            'bucket' => $_GET['bucket'] ?? null,
            'search' => $_GET['q'] ?? '',
            'installments' => Installment::overdue($_GET['bucket'] ?? null, $_GET['q'] ?? null, Auth::role() === 'operator' ? Auth::id() : null, 60),
            'operators' => User::all('operator'),
        ], is_ajax_request() ? null : 'app');
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
            $operatorId = (int) ($_POST['operator_id'] ?? 0);
            if (!$operatorId) {
                $operators = User::all('operator');
                $operatorId = count($operators) === 1 ? (int) $operators[0]['id'] : 0;
            }
            if (!$operatorId) {
                set_flash('error', 'اپراتور پیگیری را انتخاب کنید.');
                redirect('overdue');
            }
            Model::execute('UPDATE contracts SET assigned_operator_id = ? WHERE id = ?', [$operatorId, $installment['contract_id']]);
            Notification::create($operatorId, 'قسط سررسید شده', 'یک قرارداد برای پیگیری به شما ارجاع شد.', 'overdue', url('operator'));
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
            $this->authorizeInstallment($installment);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($reason === '') {
                set_flash('error', 'دلیل ارجاع به حقوقی الزامی است.');
                redirect('overdue');
            }
            try {
                LegalCase::createCase($_POST['lawyer_id'] ?? null, $installment['contract_id'], $_POST['notes'] ?? '', $reason);
                set_flash('success', 'پرونده برای واحد حقوقی ثبت شد.');
            } catch (Throwable $e) {
                set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ارجاع به حقوقی انجام نشد.');
            }
        }
        redirect('overdue');
    }

    public function followup($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        $installment = Installment::find((int) $id);
        if (!$installment) {
            set_flash('error', 'قسط پیدا نشد.');
            redirect('overdue');
        }
        $this->authorizeInstallment($installment);
        $result = trim((string) ($_POST['call_result'] ?? ''));
        if ($result === '') {
            set_flash('error', 'نتیجه تماس الزامی است.');
            redirect('overdue');
        }
        $promiseDate = parse_jalali_date($_POST['promise_payment_date'] ?? '');
        OperatorCall::createCall(
            Auth::id(),
            (int) $installment['contract_id'],
            $result,
            $_POST['notes'] ?? '',
            null,
            $promiseDate,
            (int) $installment['id']
        );
        set_flash('success', 'نتیجه پیگیری ثبت شد.');
        redirect('overdue');
    }

    public function callLog($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        $installment = Installment::find((int) $id);
        if ($installment) {
            $this->authorizeInstallment($installment);
            OperatorCall::createCall(Auth::id(), (int) $installment['contract_id'], 'تماس با مشتری', $_POST['notes'] ?? '', null, null, (int) $installment['id']);
            set_flash('success', 'تماس در سوابق پیگیری ثبت شد.');
        }
        redirect('overdue');
    }

    protected function authorizeInstallment(array $installment)
    {
        if (Auth::role() !== 'operator') {
            return;
        }
        if ((int) ($installment['assigned_operator_id'] ?? 0) === (int) Auth::id()) {
            return;
        }
        set_flash('error', 'دسترسی به این مورد پیگیری مجاز نیست.');
        redirect('overdue');
    }
}
