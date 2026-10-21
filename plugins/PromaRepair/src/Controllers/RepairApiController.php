<?php

namespace Proma\Plugins\Repair\Controllers;

use Proma\Plugins\Repair\Repositories\RepairRepository;
use Proma\Plugins\Repair\Domain\RepairMoney;
use Proma\Plugins\Repair\Repositories\RepairSettingsRepository;
use Proma\Plugins\Repair\Services\RepairService;
use Proma\Plugins\Repair\Support\RepairDate;

class RepairApiController extends \Controller
{
    private $repairs;
    private $service;

    public function __construct()
    {
        $this->repairs = new RepairRepository();
        $this->service = new RepairService($this->repairs, new RepairSettingsRepository());
        try {
            $this->service->purgeExpiredPasswords();
        } catch (\Throwable $ignored) {
        }
    }

    public function index()
    {
        $filters = ['q' => trim((string) ($_GET['q'] ?? '')), 'status' => trim((string) ($_GET['status'] ?? ''))];
        if ($this->restrictedTechnician()) {
            $filters['technician_id'] = (int) \Auth::id();
        }
        $this->json(['data' => $this->repairs->list($filters, max(1, (int) ($_GET['page'] ?? 1)))]);
    }

    public function create()
    {
        $this->verifyApiCsrf();
        if ((string) \Auth::role() === 'repair_technician') {
            $this->json(['error' => 'forbidden'], 403);
        }
        try {
            $repair = $this->service->intake($this->payload(), (int) \Auth::id());
            $this->json(['data' => $repair], 201);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show($id)
    {
        $repair = $this->repairs->find((int) $id);
        if (!$repair) {
            $this->json(['error' => 'not_found'], 404);
        }
        $this->assertScope((int) $id);
        $payload = [
            'repair' => $repair,
            'timeline' => $this->repairs->timeline((int) $id),
            'diagnoses' => $this->repairs->diagnosis((int) $id),
            'notes' => $this->repairs->notes((int) $id),
        ];
        if ((string) \Auth::role() !== 'repair_technician' && (\PluginManager::can('plugin.proma-repair.view_financial') || \PluginManager::can('plugin.proma-repair.manage_financial'))) {
            $payload['financial'] = $this->repairs->financialSummary((int) $id);
        }
        $this->json(['data' => $payload]);
    }

    public function update($id)
    {
        $this->verifyApiCsrf();
        $this->assertScope((int) $id);
        $payload = $this->payload();
        $repairFields = [];
        $deviceFields = [];
        if (array_key_exists('customer_report', $payload)) {
            $report = mb_substr(trim(strip_tags((string) $payload['customer_report'])), 0, 4000, 'UTF-8');
            if ($report === '') {
                $this->json(['error' => 'customer_report_required'], 422);
            }
            $repairFields['customer_report'] = $report;
        }
        if (array_key_exists('estimated_delivery_at', $payload)) {
            $value = trim((string) $payload['estimated_delivery_at']);
            $parsed = $value !== '' ? RepairDate::legacyDateTime($value) : null;
            if ($value !== '' && !$parsed) {
                $this->json(['error' => 'invalid_estimated_delivery_at'], 422);
            }
            $repairFields['estimated_delivery_at'] = $parsed;
        }
        $deviceInput = is_array($payload['device'] ?? null) ? $payload['device'] : [];
        foreach (['brand' => 100, 'model' => 150, 'color' => 80, 'imei' => 40, 'serial_number' => 100, 'storage_capacity' => 40, 'ram_capacity' => 40, 'carrier' => 80, 'sim_number' => 40, 'condition_notes' => 2000] as $key => $max) {
            if (array_key_exists($key, $deviceInput)) {
                $deviceFields[$key] = mb_substr(trim(strip_tags((string) $deviceInput[$key])), 0, $max, 'UTF-8');
            }
        }
        if ((string) \Auth::role() === 'repair_technician') {
            $repairFields = [];
            $deviceFields = array_intersect_key($deviceFields, ['condition_notes' => true]);
        }
        if (array_key_exists('device_type', $deviceInput)) {
            $types = ['mobile', 'tablet', 'laptop', 'smartwatch', 'headphones', 'console', 'accessory', 'other'];
            if (!in_array($deviceInput['device_type'], $types, true)) {
                $this->json(['error' => 'invalid_device_type'], 422);
            }
            $deviceFields['device_type'] = $deviceInput['device_type'];
        }
        try {
            $this->repairs->updateFields((int) $id, (int) \Auth::id(), $repairFields, $deviceFields);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
        $this->json(['data' => $this->repairs->find((int) $id)]);
    }

    public function status($id)
    {
        $this->verifyApiCsrf();
        $this->assertScope((int) $id);
        $payload = $this->payload();
        try {
            $this->repairs->transition((int) $id, trim((string) ($payload['status'] ?? '')), (int) \Auth::id(), trim((string) ($payload['note'] ?? '')));
            $this->json(['data' => ['ok' => true]]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function payment($id)
    {
        $this->verifyApiCsrf();
        if ((string) \Auth::role() === 'repair_technician') {
            $this->json(['error' => 'forbidden'], 403);
        }
        $this->assertScope((int) $id);
        $payload = $this->payload();
        try { $amount = RepairMoney::input($payload['amount'] ?? 0); }
        catch (\InvalidArgumentException $e) { $this->json(['error' => 'invalid_amount'], 422); return; }
        if ($amount <= 0) {
            $this->json(['error' => 'invalid_amount'], 422);
        }
        $this->repairs->addPayment((int) $id, (int) \Auth::id(), [
            'amount' => $amount,
            'payment_type' => in_array(($payload['payment_type'] ?? ''), ['deposit', 'second', 'final', 'other'], true) ? $payload['payment_type'] : 'deposit',
            'payment_method' => in_array(($payload['payment_method'] ?? ''), ['cash', 'card', 'transfer', 'pos', 'other'], true) ? $payload['payment_method'] : 'cash',
            'reference' => trim(strip_tags((string) ($payload['reference'] ?? ''))),
            'note' => trim(strip_tags((string) ($payload['note'] ?? ''))),
            'paid_at' => date('Y-m-d H:i:s'),
        ]);
        $this->json(['data' => ['ok' => true]], 201);
    }

    public function expense($id)
    {
        $this->verifyApiCsrf();
        if ((string) \Auth::role() === 'repair_technician') {
            $this->json(['error' => 'forbidden'], 403);
        }
        $this->assertScope((int) $id);
        $payload = $this->payload();
        try { $amount = RepairMoney::input($payload['amount'] ?? 0); }
        catch (\InvalidArgumentException $e) { $this->json(['error' => 'invalid_amount'], 422); return; }
        if ($amount <= 0) {
            $this->json(['error' => 'invalid_amount'], 422);
        }
        $this->repairs->addExpense((int) $id, (int) \Auth::id(), [
            'amount' => $amount,
            'description' => mb_substr(trim(strip_tags((string) ($payload['description'] ?? 'هزینه تعمیر'))), 0, 255, 'UTF-8'),
            'expense_type' => in_array(($payload['expense_type'] ?? ''), ['technician_cost', 'spare_part', 'transport', 'outsourcing', 'miscellaneous'], true) ? $payload['expense_type'] : 'miscellaneous',
            'technician_user_id' => max(0, (int) ($payload['technician_user_id'] ?? 0)),
        ]);
        $this->json(['data' => ['ok' => true]], 201);
    }

    public function technicianExpense($id)
    {
        $this->verifyApiCsrf();
        if ((string) \Auth::role() !== 'repair_technician') {
            $this->json(['error' => 'forbidden'], 403);
        }
        $this->assertScope((int) $id);
        $payload = $this->payload();
        try { $amount = RepairMoney::input($payload['amount'] ?? 0); }
        catch (\InvalidArgumentException $e) { $this->json(['error' => 'invalid_amount'], 422); return; }
        $type = (string) ($payload['expense_type'] ?? '');
        if ($amount <= 0 || !in_array($type, ['technician_cost', 'spare_part'], true)) {
            $this->json(['error' => 'invalid_expense'], 422);
        }
        $this->repairs->addExpense((int) $id, (int) \Auth::id(), [
            'amount' => $amount,
            'description' => mb_substr(trim(strip_tags((string) ($payload['description'] ?? 'هزینه تعمیر'))), 0, 255, 'UTF-8'),
            'expense_type' => $type,
            'technician_user_id' => (int) \Auth::id(),
        ]);
        $this->json(['data' => ['ok' => true]], 201);
    }

    public function assign($id)
    {
        $this->verifyApiCsrf();
        if ((string) \Auth::role() === 'repair_technician') {
            $this->json(['error' => 'forbidden'], 403);
        }
        $payload = $this->payload();
        try {
            $this->repairs->assign((int) $id, (int) ($payload['technician_user_id'] ?? 0), (int) \Auth::id(), (string) ($payload['assignment_role'] ?? 'primary'));
            $this->json(['data' => ['ok' => true]], 201);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function customerIndex()
    {
        $this->requireCustomer();
        $settings = (new RepairSettingsRepository())->all();
        $this->json(['data' => $this->repairs->customerList((int) \Auth::id(), true, $settings['show_customer_delivery_estimate'] === '1')]);
    }

    public function customerShow($id)
    {
        $this->requireCustomer();
        $settings = (new RepairSettingsRepository())->all();
        $repair = $this->repairs->customerDetail((int) $id, (int) \Auth::id(), true, $settings['show_customer_delivery_estimate'] === '1', $settings['show_customer_timeline'] === '1', $settings['show_customer_warranty'] === '1');
        if (!$repair) {
            $this->json(['error' => 'not_found'], 404);
        }
        $this->json(['data' => $repair]);
    }

    private function payload(): array
    {
        if (isset($_POST['_csrf'])) {
            return $_POST;
        }
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function verifyApiCsrf(): void
    {
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? ''));
        if (!\Csrf::validate($token)) {
            $this->json(['error' => 'csrf_validation_failed'], 419);
        }
    }

    private function requireCustomer(): void
    {
        if ((string) \Auth::role() !== 'customer') {
            $this->json(['error' => 'forbidden'], 403);
        }
    }

    private function assertScope(int $id): void
    {
        if ($this->restrictedTechnician() && !$this->repairs->isAssigned($id, (int) \Auth::id())) {
            $this->json(['error' => 'not_found'], 404);
        }
    }

    private function restrictedTechnician(): bool
    {
        if ((string) \Auth::role() === 'repair_technician') {
            return true;
        }
        if ((string) \Auth::role() === 'admin' || \PluginManager::can('plugin.proma-repair.manage_orders')) {
            return false;
        }
        return (bool) \Model::fetch('SELECT id FROM proma_repair_technicians WHERE user_id=? AND active=1 LIMIT 1', [(int) \Auth::id()]);
    }
}
