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
        $itemCount = 0;
        if ($contractIds) {
            try {
                $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
                $itemCount = (int) (Model::fetch("SELECT COUNT(*) AS total FROM contract_items WHERE contract_id IN ({$placeholders})", $contractIds)['total'] ?? 0);
            } catch (Throwable $e) {
                $itemCount = 0;
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
                $totalPaid += normalize_money($payment['paid_total'] ?? 0);
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
                'next_due_date' => $stat['next_due_date'] ?? null,
                'last_payment_date' => $stat['last_payment_date'] ?? null,
            ];
            $principal = normalize_money($contract['principal_amount'] ?? 0);
            $contract['history']['remaining_total'] = max(0, $principal - $contract['history']['paid_total']);
            $contract['history']['progress'] = $principal > 0 ? min(100, (int) round(($contract['history']['paid_total'] / $principal) * 100)) : 0;
        }
        unset($contract);
        $this->render('portal/history', [
            'title' => 'سوابق خرید',
            'contracts' => $contracts,
            'payments' => Payment::recentForCustomer($customerId, 30),
            'timeline' => Installment::all(['customer_id' => $customerId]),
            'summary' => [
                'contracts' => count($contracts),
                'purchases' => $itemCount ?: count($contracts),
                'last_purchase' => $contracts[0] ?? null,
                'statuses' => $statusCounts,
                'total_paid' => $totalPaid,
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
