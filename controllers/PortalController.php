<?php

class PortalController extends Controller
{
    public function index()
    {
        $this->requireRole('customer');
        redirect('dashboard');
    }

    public function contracts()
    {
        $this->requireRole('customer');
        $this->render('dashboard/customer', [
            'title' => 'قراردادهای من',
            'contracts' => Contract::all(['customer_id' => Auth::id()]),
            'installments' => [],
            'medals' => [],
            'socialLinks' => configured_social_links(Settings::allKeyed()),
            'givenGuarantees' => Contract::all(['guarantor_id' => Auth::id()]),
            'receivedGuarantees' => Model::fetchAll(
                "SELECT c.contract_number, c.id AS contract_id, u.full_name, u.mobile, u.national_id
                 FROM contracts c
                 JOIN contract_guarantors cg ON cg.contract_id = c.id
                 JOIN users u ON u.id = cg.guarantor_id
                 WHERE c.customer_id = ?
                 ORDER BY c.id DESC, u.full_name",
                [Auth::id()]
            ),
        ]);
    }

    public function installments()
    {
        $this->requireRole('customer');
        redirect('installments/panel');
    }

    public function history()
    {
        $this->requireRole('customer');
        $customerId = Auth::id();
        $contracts = Contract::all(['customer_id' => $customerId]);
        $contractIds = array_map('intval', array_column($contracts, 'id'));
        $statusCounts = [];
        foreach ($contracts as $contract) {
            $status = $contract['status'] ?? 'unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        $itemsByContract = [];
        if ($contractIds) {
            try {
                $itemsByContract = ContractDocument::itemsForContracts($contractIds);
            } catch (Throwable $e) {
                $itemsByContract = [];
            }
        }
        $historyByContract = [];
        $totalPaid = 0.0;
        if ($contractIds) {
            $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
            $installmentStats = Model::fetchAll(
                "SELECT contract_id,
                    COUNT(*) AS installment_count,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_installment_count,
                    SUM(CASE WHEN status NOT IN ('paid', 'cancelled') THEN 1 ELSE 0 END) AS open_installment_count,
                    SUM(CASE WHEN status NOT IN ('paid', 'cancelled') AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_installment_count,
                    SUM(COALESCE(base_amount, 0)) AS installment_total,
                    SUM(COALESCE(paid_amount, 0)) AS installment_paid,
                    MIN(CASE WHEN status NOT IN ('paid', 'cancelled') THEN due_date END) AS next_due_date
                 FROM installments WHERE contract_id IN ({$placeholders}) GROUP BY contract_id",
                $contractIds
            );
            foreach ($installmentStats as $stat) $historyByContract[(int) $stat['contract_id']] = $stat;
            $paymentsByContract = Model::fetchAll(
                "SELECT contract_id, SUM(amount) AS paid_total, MAX(COALESCE(payment_date, DATE(paid_at), DATE(created_at))) AS last_payment_date
                 FROM payments WHERE contract_id IN ({$placeholders}) AND status = 'paid' AND COALESCE(is_corrected, 0) = 0
                 GROUP BY contract_id",
                $contractIds
            );
            foreach ($paymentsByContract as $payment) {
                $contractId = (int) $payment['contract_id'];
                $historyByContract[$contractId] = array_merge($historyByContract[$contractId] ?? [], $payment);
            }
        }
        foreach ($contracts as &$contract) {
            $stat = $historyByContract[(int) $contract['id']] ?? [];
            $contract['history'] = [
                'installment_count' => (int) ($stat['installment_count'] ?? 0),
                'paid_installment_count' => (int) ($stat['paid_installment_count'] ?? 0),
                'open_installment_count' => (int) ($stat['open_installment_count'] ?? 0),
                'overdue_installment_count' => (int) ($stat['overdue_installment_count'] ?? 0),
                'paid_total' => normalize_money($stat['paid_total'] ?? $stat['installment_paid'] ?? 0),
                'installment_total' => normalize_money($stat['installment_total'] ?? 0),
                'installment_paid_total' => normalize_money($stat['installment_paid'] ?? 0),
                'next_due_date' => $stat['next_due_date'] ?? null,
                'last_payment_date' => $stat['last_payment_date'] ?? null,
                'item_count' => count($itemsByContract[(int) $contract['id']] ?? []),
            ];
            $totalPaid += $contract['history']['paid_total'];
            $principal = normalize_money($contract['principal_amount'] ?? 0);
            $installmentTotal = $contract['history']['installment_total'];
            $paidInstallments = $contract['history']['installment_paid_total'];
            $contract['history']['remaining_total'] = $installmentTotal > 0
                ? max(0, $installmentTotal - $paidInstallments)
                : max(0, $principal - $contract['history']['paid_total']);
            $progressBase = $installmentTotal > 0 ? $installmentTotal : $principal;
            $progressPaid = $installmentTotal > 0 ? $paidInstallments : $contract['history']['paid_total'];
            $contract['history']['progress'] = $progressBase > 0 ? min(100, (int) round(($progressPaid / $progressBase) * 100)) : 0;
            $contract['items'] = $itemsByContract[(int) $contract['id']] ?? [];
        }
        unset($contract);

        $query = trim((string) ($_GET['q'] ?? ''));
        $statusFilter = trim((string) ($_GET['status'] ?? ''));
        if ($statusFilter !== '' && !isset($statusCounts[$statusFilter])) {
            $statusFilter = '';
        }
        $searchTerms = array_values(array_unique(array_filter([$query, to_english_digits($query)])));
        $filteredContracts = array_values(array_filter($contracts, static function ($contract) use ($searchTerms, $statusFilter) {
            if ($statusFilter !== '' && (string) ($contract['status'] ?? '') !== $statusFilter) {
                return false;
            }
            if (!$searchTerms) {
                return true;
            }
            $searchable = [(string) ($contract['contract_number'] ?? ''), (string) ($contract['description'] ?? '')];
            foreach (($contract['items'] ?? []) as $item) {
                $searchable[] = (string) ($item['product_model'] ?? '');
                $searchable[] = (string) ($item['description'] ?? '');
            }
            foreach ($searchable as $value) {
                foreach ($searchTerms as $searchTerm) {
                    if ($value !== '' && mb_stripos($value, $searchTerm, 0, 'UTF-8') !== false) {
                        return true;
                    }
                }
            }
            return false;
        }));
        $perPage = 6;
        $pages = max(1, (int) ceil(count($filteredContracts) / $perPage));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        $visibleContracts = array_slice($filteredContracts, ($page - 1) * $perPage, $perPage);
        $itemCount = array_sum(array_map(static fn ($contract) => count($contract['items'] ?? []), $contracts));
        $totalBalance = array_sum(array_map(static fn ($contract) => (float) ($contract['history']['remaining_total'] ?? 0), $contracts));
        $overdueInstallments = array_sum(array_map(static fn ($contract) => (int) ($contract['history']['overdue_installment_count'] ?? 0), $contracts));
        $this->render('portal/history', [
            'title' => 'سوابق خرید',
            'contracts' => $visibleContracts,
            'query' => $query,
            'statusFilter' => $statusFilter,
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => count($filteredContracts), 'per_page' => $perPage],
            'summary' => [
                'contracts' => count($contracts),
                'purchases' => $itemCount ?: count($contracts),
                'last_purchase' => $contracts[0] ?? null,
                'statuses' => $statusCounts,
                'total_paid' => $totalPaid,
                'total_balance' => $totalBalance,
                'items' => $itemCount,
                'overdue_installments' => $overdueInstallments,
                'active_contracts' => (int) ($statusCounts['active'] ?? 0),
                'completed_contracts' => (int) ($statusCounts['completed'] ?? $statusCounts['closed'] ?? 0),
                'cancelled_contracts' => (int) ($statusCounts['cancelled'] ?? 0),
            ],
        ]);
    }

    public function guaranteed()
    {
        $this->requireRole('customer');
        $result = Contract::paginated([
            'guarantor_id' => Auth::id(),
            'search' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'per_page' => 24,
        ]);
        $this->render('contracts/index', [
            'title' => 'قراردادهای ضمانت شده',
            'contracts' => $result['items'],
            'pagination' => $result,
            'customers' => [],
            'operators' => [],
            'settings' => Settings::allKeyed(),
            'readOnly' => true,
        ], is_ajax_request() ? null : 'app');
    }
}
