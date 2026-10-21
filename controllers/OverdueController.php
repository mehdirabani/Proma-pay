<?php

class OverdueController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $criteria = OverdueFilterCriteria::fromRequest($_GET);
        $operatorId = Auth::role() === 'operator' ? Auth::id() : null;
        $result = OverdueAggregationService::paginated($criteria, $operatorId);
        $contractIds = array_column($result['items'], 'contract_id');
        $contractContacts = Contract::contactDirectoryForContracts($contractIds);
        $followupHistoryByContract = [];
        foreach (OperatorCall::recentForContracts($contractIds, 5) as $call) {
            $followupHistoryByContract[(int) ($call['contract_id'] ?? 0)][] = $call;
        }
        if (!is_ajax_request() && $criteria->page !== (int) $result['page']) {
            $params = $criteria->queryParameters(false);
            $params['page'] = (int) $result['page'];
            redirect('overdue', $params);
        }
        $this->render('overdue/index', [
            'title' => 'صف پیگیری سررسیدهای گذشته',
            'criteria' => $criteria,
            'overdueContracts' => $result['items'],
            'pagination' => $result,
            'contractContacts' => $contractContacts,
            'followupHistoryByContract' => $followupHistoryByContract,
            'operators' => User::all('operator'),
            'lawyers' => User::all('lawyer'),
        ], is_ajax_request() ? null : 'app');
    }

    public function export()
    {
        $this->requireRole(['admin', 'operator']);
        $criteria = OverdueFilterCriteria::fromRequest($_GET);
        $criteria->page = 1;
        $criteria->perPage = 100;
        $operatorId = Auth::role() === 'operator' ? (int) Auth::id() : null;
        SpreadsheetHelper::downloadPaginatedXlsx(
            'سررسیدهای-گذشته.xlsx',
            'سررسیدهای گذشته',
            ['شماره قرارداد', 'مشتری', 'موبایل', 'تعداد اقساط معوق', 'قدیمی‌ترین سررسید', 'بیشترین تأخیر (روز)', 'مانده اصل اقساط معوق (تومان)', 'جریمه عادی قابل پرداخت (تومان)', 'جریمه حقوقی قابل پرداخت (تومان)', 'هزینه حقوقی قابل وصول (تومان)', 'مجموع بدهی سررسیدشده (تومان)', 'تعداد پرونده حقوقی'],
            static function (int $page, int $perPage) use ($criteria, $operatorId): array {
                $pageCriteria = clone $criteria;
                $pageCriteria->page = $page;
                $pageCriteria->perPage = min(100, $perPage);
                $result = OverdueAggregationService::paginated($pageCriteria, $operatorId, false);
                return ['items' => $result['items'], 'total' => $result['total'], 'pages' => $result['pages']];
            },
            static fn (array $row): array => [
                (string) ($row['contract_number'] ?? ''), (string) ($row['customer_name'] ?? ''),
                (string) ($row['mobile'] ?? ''), (int) ($row['overdue_count'] ?? 0),
                jdate($row['oldest_due_date'] ?? ''), (int) ($row['max_overdue_days'] ?? 0),
                normalize_money($row['overdue_installments_total'] ?? 0),
                normalize_money($row['outstanding_normal_late_fees'] ?? 0),
                normalize_money($row['outstanding_legal_late_fees'] ?? 0),
                normalize_money($row['outstanding_legal_costs'] ?? 0),
                normalize_money($row['total_overdue_amount'] ?? $row['estimated_payable'] ?? 0), (int) ($row['legal_case_count'] ?? 0),
            ],
            100
        );
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
