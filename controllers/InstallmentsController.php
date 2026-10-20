<?php

class InstallmentsController extends Controller
{
    public function quickPaymentLookup()
    {
        $this->requireRole('admin');
        $query = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($query, 'UTF-8') < 3) {
            $this->json(['ok' => true, 'contracts' => []]);
        }
        $items = Installment::filtered(['search' => $query, 'per_page' => 100])['items'];
        $contracts = [];
        foreach ($items as $item) {
            if (empty($item['payment_allowed'])) continue;
            $contractId = (int) ($item['contract_id'] ?? 0);
            if ($contractId <= 0) continue;
            if (!isset($contracts[$contractId])) {
                $contracts[$contractId] = [
                    'contract_id' => $contractId,
                    'contract_number' => (string) ($item['contract_number'] ?? ''),
                    'customer_name' => (string) ($item['customer_name'] ?? ''),
                    'national_id' => (string) ($item['national_id'] ?? ''),
                    'mobile' => (string) ($item['mobile'] ?? ''),
                    'installments' => [],
                ];
            }
            $contracts[$contractId]['installments'][] = [
                'id' => (int) $item['id'],
                'number' => (int) ($item['installment_number'] ?? 0),
                'due_date' => jdate($item['due_date'] ?? ''),
                'payable' => (int) normalize_money($item['final_payable'] ?? $item['payable'] ?? 0),
                'status' => (string) ($item['status'] ?? ''),
            ];
        }
        $this->json(['ok' => true, 'contracts' => array_values($contracts)]);
    }

    public function index()
    {
        $this->requireRole('admin');
        $filters = $this->filters();
        $result = Installment::filtered($filters);
        $contractContacts = Contract::contactDirectoryForContracts(array_column($result['items'], 'contract_id'));
        $this->render('installments/index', [
            'title' => 'مدیریت اقساط',
            'installments' => $result['items'],
            'filters' => $filters,
            'pagination' => $result,
            'installmentSummary' => Installment::summary($filters),
            'contractContacts' => $contractContacts,
            'contracts' => [],
            'defaultDueDate' => jdate(FinanceHelper::addMonths(date('Y-m-d'), 1)),
        ], is_ajax_request() ? null : 'app');
    }

    public function export()
    {
        $this->requireRole('admin');
        $filters = $this->filters();
        SpreadsheetHelper::downloadPaginatedXlsx(
            'اقساط.xlsx',
            'اقساط',
            ['شماره قرارداد', 'مشتری', 'کد ملی', 'موبایل', 'شماره قسط', 'سررسید', 'مبلغ قابل پرداخت (تومان)', 'پرداخت‌شده (تومان)', 'مانده اصل (تومان)', 'وضعیت'],
            static function (int $page, int $perPage) use ($filters): array {
                $pageFilters = $filters;
                $pageFilters['page'] = $page;
                $pageFilters['per_page'] = $perPage;
                return Installment::filtered($pageFilters);
            },
            static fn (array $row): array => [
                (string) ($row['contract_number'] ?? ''), (string) ($row['customer_name'] ?? ''),
                (string) ($row['national_id'] ?? ''), (string) ($row['mobile'] ?? ''),
                (int) ($row['installment_number'] ?? 0), jdate($row['due_date'] ?? ''),
                normalize_money($row['final_payable'] ?? $row['payable'] ?? $row['base_amount'] ?? 0),
                normalize_money($row['paid_amount'] ?? 0), normalize_money($row['remaining_principal'] ?? $row['remaining_amount'] ?? 0),
                status_label($row['status'] ?? ''),
            ]
        );
    }

    public function panel()
    {
        $this->requireRole('customer');
        $customerInstallments = Installment::all(['customer_id' => Auth::id()]);
        $gatewayReviewRows = [];
        try {
            if (SchemaGuard::columnsExist('payment_groups', ['contract_id', 'customer_id', 'status', 'gateway_verified_amount', 'gateway_track_id', 'created_at'])) {
                $gatewayReviewRows = Model::fetchAll(
                    "SELECT pg.contract_id, pg.gateway_verified_amount, pg.gateway_track_id, c.contract_number
                     FROM payment_groups pg JOIN contracts c ON c.id = pg.contract_id
                     WHERE pg.customer_id = ? AND pg.status = 'review_required' ORDER BY pg.created_at DESC",
                    [(int) Auth::id()]
                );
            }
        } catch (Throwable $e) {
            if (class_exists('PluginRegistry')) {
                try { PluginRegistry::logRuntimeError('installments.panel.gateway_review', $e); } catch (Throwable $ignored) {}
            }
        }
        $gatewayReviewsByContract = [];
        foreach ($gatewayReviewRows as $reviewRow) {
            $gatewayReviewsByContract[(int) $reviewRow['contract_id']][] = $reviewRow;
        }
        $installmentGroups = [];
        foreach ($customerInstallments as $item) {
            if (empty($item['payment_allowed'])) {
                continue;
            }
            $contractId = (int) ($item['contract_id'] ?? 0);
            if (!isset($installmentGroups[$contractId])) {
                $installmentGroups[$contractId] = ['contract_id' => $contractId, 'contract_number' => $item['contract_number'] ?? '', 'items' => [], 'total' => 0];
            }
            $installmentGroups[$contractId]['items'][] = $item;
            $installmentGroups[$contractId]['total'] += normalize_money($item['final_payable'] ?? $item['payable'] ?? 0);
        }
        foreach ($installmentGroups as &$group) {
            $group['settlement_preview'] = PaymentAllocationService::quote($group['items'], date('Y-m-d'));
            $group['total'] = normalize_money($group['settlement_preview']['full_settlement_total'] ?? 0);
        }
        unset($group);
        $legalCostGroups = [];
        foreach (LegalCaseCostService::outstandingChargeableForCustomer((int) Auth::id()) as $legalCost) {
            $contractId = (int) ($legalCost['contract_id'] ?? 0);
            if ($contractId <= 0) continue;
            if (!isset($legalCostGroups[$contractId])) {
                $legalCostGroups[$contractId] = [
                    'contract_id' => $contractId,
                    'contract_number' => (string) ($legalCost['contract_number'] ?? $contractId),
                    'total' => 0,
                ];
            }
            $legalCostGroups[$contractId]['total'] += max(0, normalize_money($legalCost['amount_toman'] ?? 0) - normalize_money($legalCost['paid_amount_toman'] ?? 0));
        }
        $this->render('installments/index', [
            'title' => 'پنل اقساط من',
            'installments' => $customerInstallments,
            'installmentGroups' => array_values($installmentGroups),
            'legalCostGroups' => array_values($legalCostGroups),
            'gatewayReviewsByContract' => $gatewayReviewsByContract,
            'contracts' => [],
            'customerMode' => true,
            'installmentsRoute' => 'installments/panel',
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $redirectTo = $this->redirectRoute();
        $dueDate = parse_jalali_date($_POST['due_date'] ?? '');
        if (!$dueDate) {
            set_flash('error', 'تاریخ سررسید معتبر نیست.');
            redirect($redirectTo);
        }
        $customerDescription = trim((string) ($_POST['customer_description'] ?? $_POST['notes'] ?? ''));
        if ($customerDescription === '') {
            set_flash('error', 'توضیح قابل نمایش برای مشتری الزامی است.');
            redirect($redirectTo);
        }
        if ((int) ($_POST['contract_id'] ?? 0) <= 0) {
            set_flash('error', 'قرارداد معتبر انتخاب نشده است.');
            redirect($redirectTo);
        }
        try {
            Installment::createCustom(
                (int) $_POST['contract_id'],
                $dueDate,
                $_POST['base_amount'],
                $customerDescription,
                $_POST['guarantee_serial'] ?? '',
                $_POST['custom_title'] ?? '',
                $_POST['internal_note'] ?? '',
                isset($_POST['customer_visible'])
            );
            set_flash('success', 'قسط سفارشی ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت قسط انجام نشد.');
        }
        redirect($redirectTo);
    }

    public function adjust($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        Installment::adjust((int) $id, $_POST['manual_penalty_adjustment'] ?? 0, $_POST['manual_reward_adjustment'] ?? 0);
        set_flash('success', 'جریمه و پاداش قسط به‌روزرسانی شد.');
        redirect('installments');
    }

    public function payment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $redirectTo = $this->redirectRoute();
        $requestUuid = PaymentRequest::normalizeUuid($_POST['payment_request_uuid'] ?? '');
        try {
            $installment = Installment::find((int) $id);
            if (!$installment) {
                set_flash('error', 'قسط پیدا نشد.');
                redirect('installments');
            }
            $amount = normalize_money($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('مبلغ پرداخت معتبر نیست.');
            }
            $paymentDate = parse_jalali_date($_POST['payment_date'] ?? '') ?: date('Y-m-d');
            $paymentTime = normalize_time($_POST['payment_time'] ?? null) ?: date('H:i');
            $includeLegalCosts = !empty($_POST['include_legal_costs']);
            $scope = $includeLegalCosts ? 'contract_legal_first' : 'forward';
            $group = PaymentGroupService::create(
                (int) $installment['contract_id'],
                [(int) $id],
                $amount,
                Auth::id(),
                'manual',
                trim((string) ($_POST['description'] ?? 'پرداخت دستی قسط')),
                false,
                $requestUuid,
                null,
                $paymentDate,
                $paymentTime,
                $scope
            );
            if (class_exists('SystemOutbox')) {
                try {
                    SystemOutbox::safeEnqueueNotification($installment['customer_id'], 'پرداخت جدید ثبت شد', 'یک پرداخت برای اقساط شما ثبت شد.', 'payment', url('installments/panel'), 'payment_group', (int) ($group['id'] ?? 0));
                    SystemOutbox::processPending(10, 'payment_group', (int) ($group['id'] ?? 0));
                } catch (Throwable $outboxError) {
                    if (class_exists('PluginRegistry')) {
                        PluginRegistry::logRuntimeError('payment.manual.outbox', $outboxError);
                    }
                }
            }
            set_flash('success', $includeLegalCosts ? 'پرداخت اقساط و هزینه‌های حقوقی ثبت شد.' : 'پرداخت دستی ثبت شد؛ مازاد به اقساط بعدی همین قرارداد تخصیص یافت.');
        } catch (Throwable $e) {
            PaymentRequest::fail($requestUuid, $e);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت پرداخت انجام نشد. اگر مبلغ از حساب مشتری کم شده، گزارش پرداخت را بررسی کنید.');
        }
        redirect($redirectTo);
    }

    public function previewPayment()
    {
        $this->requireRole('admin');
        $installment = Installment::find((int) ($_GET['installment_id'] ?? 0));
        if (!$installment) {
            $this->json(['ok' => false, 'message' => 'قسط پیدا نشد.'], 404);
        }
        $paymentDate = parse_jalali_date($_GET['payment_date'] ?? '') ?: date('Y-m-d');
        $preview = FinanceHelper::paymentPreview(
            $installment,
            Payment::forInstallment((int) $installment['id']),
            Settings::allKeyed(),
            $_GET['payment_amount'] ?? 0,
            $paymentDate
        );
        $preview['formatted'] = [
            'base_amount' => money_toman($preview['base_amount']),
            'paid_amount' => money_toman($preview['paid_amount']),
            'remaining_before_payment' => money_toman($preview['remaining_before_payment']),
            'calculated_penalty' => money_toman($preview['calculated_penalty']),
            'calculated_penalty_html' => penalty_display_html($preview),
            'calculated_reward' => money_toman($preview['calculated_reward']),
            'payable_on_payment_date' => money_toman($preview['payable_on_payment_date']),
            'remaining_after_payment' => money_toman($preview['remaining_after_payment']),
        ];
        $preview['status_label'] = status_label($preview['final_status']);
        $this->json(['ok' => true, 'preview' => $preview]);
    }

    public function markPaid($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (!Installment::markPaid((int) $id, Auth::id())) {
            set_flash('error', 'قسط لغو شده یا نامعتبر است.');
        } else {
            set_flash('success', 'قسط تسویه شد.');
        }
        redirect('installments');
    }

    protected function redirectRoute()
    {
        $target = $_POST['redirect_to'] ?? '';
        if ($target === 'overdue') {
            return 'overdue';
        }
        if ($target === 'contract' && !empty($_POST['contract_id'])) {
            return 'contracts/show/' . (int) $_POST['contract_id'];
        }
        if ($target === 'contracts') {
            return 'contracts';
        }
        return 'installments';
    }

    protected function filters()
    {
        $status = $_GET['status'] ?? '';
        $allowedStatuses = ['pending', 'partial', 'paid', 'overdue', 'referred', 'corrected', 'cancelled'];
        $paymentState = $_GET['payment_state'] ?? '';
        $allowedStates = ['paid', 'unpaid', 'overdue', 'custom'];
        $tab = $_GET['tab'] ?? 'active';
        $allowedTabs = ['active', 'today', 'overdue', 'partial', 'paid', 'all'];
        $tab = in_array($tab, $allowedTabs, true) ? $tab : 'active';
        $perPage = (int) to_english_digits($_GET['per_page'] ?? 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $sort = (string) ($_GET['sort'] ?? 'financial');
        $allowedSorts = ['financial', 'due_asc', 'due_desc', 'amount_desc', 'amount_asc', 'customer_asc', 'customer_desc'];
        $filters = [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'customer_name' => trim((string) ($_GET['customer_name'] ?? '')),
            'contract_number' => trim((string) ($_GET['contract_number'] ?? '')),
            'mobile' => trim((string) ($_GET['mobile'] ?? '')),
            'national_id' => trim((string) ($_GET['national_id'] ?? '')),
            'status' => in_array($status, $allowedStatuses, true) ? $status : '',
            'due_from' => parse_jalali_date($_GET['due_from'] ?? '') ?: '',
            'due_to' => parse_jalali_date($_GET['due_to'] ?? '') ?: '',
            'amount_min' => trim((string) ($_GET['amount_min'] ?? '')),
            'amount_max' => trim((string) ($_GET['amount_max'] ?? '')),
            'payment_state' => in_array($paymentState, $allowedStates, true) ? $paymentState : '',
            'exclude_legal_cases' => in_array(strtolower(trim((string) ($_GET['exclude_legal_cases'] ?? ''))), ['1', 'true', 'yes', 'on'], true),
            'page' => max(1, (int) to_english_digits($_GET['page'] ?? 1)),
            'per_page' => $perPage,
            'sort' => in_array($sort, $allowedSorts, true) ? $sort : 'financial',
            'tab' => $tab,
            'due_today' => false,
        ];
        // Explicit legacy query values always win; otherwise tab is the one
        // source of truth for the list and its result count.
        if ($status === '' && $filters['payment_state'] === '') {
            if ($tab === 'active') $filters['payment_state'] = 'unpaid';
            if ($tab === 'today') { $filters['payment_state'] = 'unpaid'; $filters['due_today'] = true; }
            if ($tab === 'overdue') $filters['payment_state'] = 'overdue';
            if ($tab === 'partial') $filters['status'] = 'partial';
            if ($tab === 'paid') $filters['status'] = 'paid';
        }
        return $filters;
    }
}
