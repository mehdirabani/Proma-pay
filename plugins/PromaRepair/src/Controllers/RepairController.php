<?php

namespace Proma\Plugins\Repair\Controllers;

use Proma\Plugins\Repair\Domain\RepairWorkflow;
use Proma\Plugins\Repair\Domain\RepairMoney;
use Proma\Plugins\Repair\Repositories\RepairRepository;
use Proma\Plugins\Repair\Repositories\RepairSettingsRepository;
use Proma\Plugins\Repair\Services\AttachmentService;
use Proma\Plugins\Repair\Services\RepairService;
use Proma\Plugins\Repair\Support\RepairDate;
use Proma\Plugins\Repair\Support\RepairAuthorization;

class RepairController extends \Controller
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

    public function render($view, array $data = [], $layout = 'app')
    {
        if ($layout !== 'app' || !RepairAuthorization::isTechnician()) {
            parent::render($view, $data, $layout);
            return;
        }
        ob_start();
        try {
            parent::render($view, $data, null);
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $title = $data['title'] ?? 'پنل تعمیرکار';
        require dirname(__DIR__, 2) . '/views/layouts/technician.php';
    }

    public function dashboard()
    {
        $isTechnician = RepairAuthorization::isTechnician();
        if ($isTechnician) {
            $dashboardWarning = null;
            try {
                $this->assertTechnician();
            } catch (\HttpException $e) {
                throw $e;
            } catch (\Throwable $e) {
                \ErrorHandler::log('repair_technician_dashboard_profile', $e, 500);
                $dashboardWarning = 'اعتبار پروفایل تعمیرکار قابل بررسی نیست؛ migration افزونه تعمیرات را بررسی کنید.';
                $this->render('plugin:proma-repair/dashboard/technician', [
                    'title' => 'داشبورد تعمیرکار',
                    'dashboard' => ['counts' => [], 'finance' => [], 'latest' => [], 'overdue' => [], 'unassigned' => [], 'ready' => [], 'debts' => [], 'technician_performance' => []],
                    'expenses' => [], 'dashboardWarning' => $dashboardWarning, 'accountingBalance' => null,
                ]);
                return;
            }
            try {
                $data = $this->repairs->dashboard((int) \Auth::id());
            } catch (\Throwable $e) {
                \ErrorHandler::log('repair_technician_dashboard', $e, 500);
                $dashboardWarning = 'بخشی از خلاصه پرونده‌ها در دسترس نیست؛ از پیوند «تعمیرات من» استفاده کنید و مدیر سامانه را از شناسه پیگیری خطا مطلع کنید.';
                $data = ['counts' => [], 'finance' => [], 'latest' => [], 'overdue' => [], 'unassigned' => [], 'ready' => [], 'debts' => [], 'technician_performance' => []];
            }
            try {
                $expenses = $this->repairs->technicianExpenses((int) \Auth::id());
            } catch (\Throwable $e) {
                \ErrorHandler::log('repair_technician_dashboard_expenses', $e, 500);
                $dashboardWarning = 'خلاصه هزینه‌های اخیر بارگذاری نشد؛ فهرست درخواست‌های هزینه را باز کنید.';
                $expenses = [];
            }
            $this->render('plugin:proma-repair/dashboard/technician', [
                'title' => 'داشبورد تعمیرکار', 'dashboard' => $data,
                'expenses' => $expenses, 'dashboardWarning' => $dashboardWarning,
                'accountingBalance' => $this->accountingBalance(),
            ]);
            return;
        }
        $dashboardWarning = null;
        try {
            $data = $this->repairs->dashboard();
        } catch (\Throwable $e) {
            \ErrorHandler::log('repair_manager_dashboard', $e, 500);
            $dashboardWarning = 'خلاصهٔ تعمیرات موقتاً بارگذاری نشد؛ می‌توانید از فهرست پرونده‌ها استفاده کنید.';
            $data = ['counts' => [], 'finance' => [], 'latest' => [], 'overdue' => [], 'unassigned' => [], 'ready' => [], 'debts' => [], 'technician_performance' => []];
        }
        $canFinancial = !$isTechnician && (\PluginManager::can('plugin.proma-repair.view_financial') || \PluginManager::can('plugin.proma-repair.manage_financial'));
        $canProfit = !$isTechnician && \PluginManager::can('plugin.proma-repair.view_profit');
        $technicians = [];
        if (!$isTechnician && \PluginManager::can('plugin.proma-repair.assign_technicians')) {
            try {
                $technicians = $this->repairs->technicians();
            } catch (\Throwable $e) {
                \ErrorHandler::log('repair_manager_dashboard_technicians', $e, 500);
                $dashboardWarning = $dashboardWarning ?: 'فهرست تعمیرکاران موقتاً در دسترس نیست.';
            }
        }
        try {
            $settings = (new RepairSettingsRepository())->all();
        } catch (\Throwable $e) {
            \ErrorHandler::log('repair_manager_dashboard_settings', $e, 500);
            $settings = [];
            $dashboardWarning = $dashboardWarning ?: 'تنظیمات تعمیرات موقتاً در دسترس نیست.';
        }
        $this->render('plugin:proma-repair/dashboard/index', [
            'title' => 'داشبورد تعمیرات', 'dashboard' => $data, 'statuses' => RepairWorkflow::STATUSES,
            'dashboardWarning' => $dashboardWarning,
            'canFinancial' => $canFinancial, 'canProfit' => $canProfit,
            'canCreate' => !$isTechnician && \PluginManager::can('plugin.proma-repair.create_orders'),
            'canAssign' => !$isTechnician && \PluginManager::can('plugin.proma-repair.assign_technicians'),
            'technicians' => $technicians,
            'settings' => $settings, 'parentRepair' => null,
            'financial' => $canFinancial ? $this->financialDashboard($data['finance']) : null,
        ]);
    }

    private function accountingBalance()
    {
        if (!class_exists('PluginManager') || !\PluginManager::isActive('proma-accounting') || !class_exists('Proma\\Plugins\\Accounting\\Services\\AccountBalanceService')) {
            return null;
        }
        try {
            return \Proma\Plugins\Accounting\Services\AccountBalanceService::snapshot((int) \Auth::id());
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function orders()
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => \parse_jalali_date($_GET['date_from'] ?? '') ?: '',
            'date_to' => \parse_jalali_date($_GET['date_to'] ?? '') ?: '',
            'technician_id' => max(0, (int) ($_GET['technician_id'] ?? 0)),
            'device_type' => trim((string) ($_GET['device_type'] ?? '')),
            'brand' => mb_substr(trim((string) ($_GET['brand'] ?? '')), 0, 100, 'UTF-8'),
            'payment_status' => trim((string) ($_GET['payment_status'] ?? '')),
            'profitability' => trim((string) ($_GET['profitability'] ?? '')),
            'warranty' => trim((string) ($_GET['warranty'] ?? '')),
        ];
        $canFinancial = !RepairAuthorization::isTechnician() && (\PluginManager::can('plugin.proma-repair.view_financial') || \PluginManager::can('plugin.proma-repair.manage_financial'));
        $canProfit = !RepairAuthorization::isTechnician() && \PluginManager::can('plugin.proma-repair.view_profit');
        if (!$canFinancial) {
            $filters['payment_status'] = '';
        }
        if (!$canProfit) {
            $filters['profitability'] = '';
        }
        if ($this->isRestrictedTechnician()) {
            $filters['technician_id'] = \Auth::id();
        }
        $result = $this->repairs->list($filters, max(1, (int) ($_GET['page'] ?? 1)), 25);
        $this->render('plugin:proma-repair/orders/index', [
            'title' => 'پرونده‌های تعمیر', 'filters' => $filters, 'result' => $result,
            'statusLabels' => $this->statusLabels(), 'technicians' => $this->isRestrictedTechnician() ? [] : $this->repairs->technicians(),
            'canFinancial' => $canFinancial, 'canProfit' => $canProfit,
            'canCreate' => !RepairAuthorization::isTechnician() && \PluginManager::can('plugin.proma-repair.create_orders'),
        ]);
    }

    public function technicianExpenses()
    {
        $this->assertTechnician();
        $this->render('plugin:proma-repair/technicians/expenses', [
            'title' => 'هزینه‌های من', 'items' => $this->repairs->technicianExpenses((int) \Auth::id()), 'onlyRequests' => false,
        ]);
    }

    public function technicianRequests()
    {
        $this->assertTechnician();
        $this->render('plugin:proma-repair/technicians/expenses', [
            'title' => 'درخواست‌های من', 'items' => $this->repairs->technicianExpenses((int) \Auth::id(), 'pending_approval'), 'onlyRequests' => true,
        ]);
    }

    public function technicianHistory()
    {
        $this->assertTechnician();
        $filters = [
            'q' => '', 'status' => 'delivered', 'date_from' => '', 'date_to' => '',
            'technician_id' => (int) \Auth::id(), 'device_type' => '', 'brand' => '',
            'payment_status' => '', 'profitability' => '', 'warranty' => '',
        ];
        $result = $this->repairs->list($filters, max(1, (int) ($_GET['page'] ?? 1)), 25);
        $this->render('plugin:proma-repair/orders/index', [
            'title' => 'تاریخچه تعمیرات', 'filters' => $filters, 'result' => $result,
            'statusLabels' => $this->statusLabels(), 'technicians' => [], 'canFinancial' => false, 'canProfit' => false, 'canCreate' => false,
        ]);
    }

    public function technicianProfile()
    {
        $this->assertTechnician();
        $this->render('plugin:proma-repair/technicians/profile', [
            'title' => 'پروفایل تعمیرکار', 'profile' => $this->repairs->technicianProfile((int) \Auth::id()),
        ]);
    }

    public function saveTechnicianProfile()
    {
        $this->onlyPost();
        $this->assertTechnician();
        try {
            $this->repairs->saveTechnicianProfile((int) \Auth::id(), (int) \Auth::id(), $_POST);
            \set_flash('success', 'پروفایل کاری ذخیره شد.');
        } catch (\Throwable $e) {
            \set_flash('error', 'ذخیره پروفایل انجام نشد. اطلاعات را بررسی کنید.');
        }
        \redirect('plugin/repair/technician-profile');
    }

    public function expenseApprovals()
    {
        if (!RepairAuthorization::can('plugin.proma-repair.approve_expenses')) { \ErrorHandler::abort(403); }
        $this->render('plugin:proma-repair/technicians/approvals', [
            'title' => 'تأیید هزینه تعمیرکاران', 'items' => $this->repairs->pendingExpenses(),
        ]);
    }

    public function intake()
    {
        $this->assertNotRepairTechnician();
        \redirect('plugin/repair/dashboard', ['open' => 'repair-intake-modal']);
    }

    public function warrantyReturnIntake($id)
    {
        $this->assertNotRepairTechnician();
        $id = (int) $id;
        $original = $this->repairs->find($id);
        $source = $original ? $this->repairs->warrantyReturnSource($id, (int) $original['customer_id']) : null;
        if (!$source) {
            \ErrorHandler::abort(404);
        }
        \redirect('plugin/repair/detail/' . $id, ['open' => 'repair-warranty-intake-modal']);
    }

    public function customerLookup()
    {
        $this->assertNotRepairTechnician();
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = array_values(array_filter(\User::searchCustomers($query, 10), static function ($customer) {
            return (string) ($customer['status'] ?? '') === 'active';
        }));
        $this->json(['ok' => true, 'items' => array_map(static function ($customer) {
            return [
                'id' => (int) $customer['id'], 'customer_number' => (string) $customer['id'],
                'full_name' => (string) $customer['full_name'], 'mobile' => (string) $customer['mobile'],
                'national_id' => (string) ($customer['national_id'] ?? ''), 'status' => (string) ($customer['status'] ?? ''),
                'status_label' => \status_label($customer['status'] ?? ''),
            ];
        }, $items)]);
    }

    public function create()
    {
        $this->assertNotRepairTechnician();
        $this->onlyPost();
        $wantsJson = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        try {
            $input = $_POST;
            $technicianId = max(0, (int) ($input['technician_user_id'] ?? 0));
            if ($technicianId > 0 && !\PluginManager::can('plugin.proma-repair.assign_technicians')) {
                \ErrorHandler::abort(403);
            }
            $repair = $this->service->intake($input, (int) \Auth::id());
            if ($technicianId > 0) {
                try {
                    \Notification::create($technicianId, 'تعمیر جدید به شما ارجاع شد', 'یک پرونده تعمیر به فهرست کارهای شما افزوده شد.', 'repair', \url('plugin/repair/detail/' . (int) $repair['id']), 'repair-assigned:' . (int) $repair['id'] . ':' . $technicianId);
                } catch (\Throwable $notificationError) {
                    error_log('[PromaRepair][intake] Repair saved but technician notification failed: ' . $notificationError->getMessage());
                }
            }
            foreach ((array) ($_FILES['intake_images'] ?? []) as $type => $file) {
                if (!in_array((string) $type, ['front', 'back', 'damage_detail'], true)) {
                    continue;
                }
                if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                try {
                    $stored = (new AttachmentService())->store($file, (int) $repair['id']);
                    $this->repairs->storeAttachment((int) $repair['id'], (string) $type, $stored['path'], ['name' => $file['name'] ?? '', 'mime' => $stored['mime'], 'size' => $stored['size']], (int) \Auth::id());
                } catch (\Throwable $uploadError) {
                    try {
                        $this->repairs->audit((int) $repair['id'], (int) \Auth::id(), 'intake_attachment_failed', ['type' => $type, 'error_code' => 'REPAIR_INTAKE_UPLOAD_FAILED']);
                    } catch (\Throwable $auditError) {
                        error_log('[PromaRepair][intake] Repair saved; image upload and its audit record failed: ' . $auditError->getMessage());
                    }
                }
            }
            if ((string) ($repair['status'] ?? '') === 'draft') {
                if ($wantsJson) {
                    $this->json(['ok' => true, 'draft' => true, 'repair' => ['id' => (int) $repair['id'], 'tracking_code' => $repair['tracking_code']], 'url' => \url('plugin/repair/detail/' . (int) $repair['id'])]);
                }
                \set_flash('success', 'پیش‌نویس پذیرش ذخیره شد.');
                \redirect('plugin/repair/detail/' . (int) $repair['id']);
            }
            if ($wantsJson) {
                $this->json(['ok' => true, 'repair' => ['id' => (int) $repair['id'], 'tracking_code' => $repair['tracking_code']], 'url' => \url('plugin/repair/detail/' . (int) $repair['id'])]);
            }
            \set_flash('success', 'پرونده تعمیر با شماره ' . $repair['tracking_code'] . ' ثبت شد.');
            \redirect('plugin/repair/detail/' . (int) $repair['id']);
        } catch (\Throwable $e) {
            if ($e instanceof \HttpException) {
                throw $e;
            }
            $requestId = \ErrorHandler::requestId();
            if (!$e instanceof \InvalidArgumentException) {
                \ErrorHandler::log('repair_intake_create', $e, 500);
            }
            if ($wantsJson) {
                $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت پذیرش انجام نشد. شناسه پیگیری خطا: ' . $requestId;
                $status = $e instanceof \InvalidArgumentException ? 422 : 500;
                $this->json(['ok' => false, 'message' => $message, 'request_id' => $requestId], $status);
            }
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت پرونده تعمیر انجام نشد. شناسه پیگیری خطا: ' . $requestId;
            \set_flash('error', $message);
            $parentId = max(0, (int) ($_POST['parent_repair_id'] ?? 0));
            \redirect($parentId > 0 ? 'plugin/repair/detail/' . $parentId . '?open=repair-warranty-intake-modal' : 'plugin/repair/dashboard?open=repair-intake-modal');
        }
    }

    public function detail($id)
    {
        $repair = $this->getAuthorizedRepair((int) $id);
        if (!$repair) {
            \ErrorHandler::abort(404);
        }
        $isTechnician = RepairAuthorization::isTechnician();
        $canFinancial = !$isTechnician && (\PluginManager::can('plugin.proma-repair.view_financial') || \PluginManager::can('plugin.proma-repair.manage_financial'));
        $canProfit = !$isTechnician && \PluginManager::can('plugin.proma-repair.view_profit');
        $secret = null;
        if (\Auth::ensureSessionWritable()) {
            $secret = $_SESSION['repair_secret_reveals'][(int) $id] ?? null;
            unset($_SESSION['repair_secret_reveals'][(int) $id]);
            \Auth::commitSessionWrite();
        }
        $settings = (new RepairSettingsRepository())->all();
        $this->render('plugin:proma-repair/detail/index', [
            'title' => 'جزئیات تعمیر', 'repair' => $repair,
            'timeline' => $this->repairs->timeline((int) $id), 'diagnoses' => $this->repairs->diagnosis((int) $id),
            'notes' => $this->repairs->notes((int) $id), 'assignments' => $this->repairs->assignments((int) $id),
            'attachments' => $this->repairs->attachments((int) $id), 'technicians' => $this->repairs->technicians(),
            'financial' => $canFinancial ? $this->repairs->financialSummary((int) $id) : null,
            'canFinancial' => $canFinancial, 'canProfit' => $canProfit,
            'canManageFinancial' => !$isTechnician && \PluginManager::can('plugin.proma-repair.manage_financial'),
            'canRecordCosts' => $isTechnician && \PluginManager::can('plugin.proma-repair.record_costs'),
            'canApproveExpenses' => \PluginManager::can('plugin.proma-repair.approve_expenses'),
            'canSettleExpenses' => \PluginManager::can('plugin.proma-repair.settle_expenses'),
            'pendingExpenses' => \PluginManager::can('plugin.proma-repair.approve_expenses') ? $this->repairs->pendingExpensesForRepair((int) $id) : [],
            'canManage' => \PluginManager::can('plugin.proma-repair.manage_orders'),
            'canDeleteRepair' => !$isTechnician && \PluginManager::can('plugin.proma-repair.manage_orders'),
            'canAssign' => \PluginManager::can('plugin.proma-repair.assign_technicians'),
            'canChangeStatus' => \PluginManager::can('plugin.proma-repair.change_status'),
            'canPassword' => \PluginManager::can('plugin.proma-repair.view_device_password'),
            'canDeletePassword' => !$isTechnician && \PluginManager::can('plugin.proma-repair.delete_device_password'),
            'canUpload' => \PluginManager::can('plugin.proma-repair.upload_attachments'),
            'canPrint' => \PluginManager::can('plugin.proma-repair.print_receipt'),
            'statuses' => RepairWorkflow::STATUSES, 'statusLabels' => $this->statusLabels(), 'revealedPassword' => $secret,
            'warranty' => \Model::fetch('SELECT * FROM proma_repair_warranties WHERE repair_id=? LIMIT 1', [(int) $id]),
            'settings' => $settings,
            'parentRepair' => $this->repairs->warrantyReturnSource((int) $id, (int) $repair['customer_id']),
        ]);
    }

    public function changeStatus($id)
    {
        $this->onlyPost();
        $id = (int) $id;
        $this->assertAssignedWhenTechnician($id);
        $status = trim((string) ($_POST['status'] ?? ''));
        try {
            $this->repairs->transition($id, $status, (int) \Auth::id(), trim((string) ($_POST['note'] ?? '')));
            \set_flash('success', 'وضعیت تعمیر به‌روزرسانی شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'تغییر وضعیت تعمیر انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function diagnosis($id)
    {
        $this->onlyPost();
        $id = (int) $id;
        $this->assertAssignedWhenTechnician($id);
        $diagnosis = trim(strip_tags((string) ($_POST['diagnosis'] ?? '')));
        if ($diagnosis === '') {
            \set_flash('error', 'تشخیص تعمیرکار را وارد کنید.');
            \redirect('plugin/repair/detail/' . $id);
        }
        try {
            $proposedAmount = RepairMoney::input($_POST['proposed_amount'] ?? 0);
            $estimatedHoursInput = trim((string) ($_POST['estimated_hours'] ?? ''));
            $estimatedHours = $estimatedHoursInput !== '' ? (float) \to_english_digits($estimatedHoursInput) : '';
            if ($estimatedHours !== '' && ($estimatedHours <= 0 || $estimatedHours > 8760)) {
                throw new \InvalidArgumentException('مدت تخمینی باید بین نیم ساعت تا یک سال باشد.');
            }
            $this->repairs->addDiagnosis($id, (int) \Auth::id(), [
                'diagnosis' => mb_substr($diagnosis, 0, 4000, 'UTF-8'),
                'proposed_amount' => $proposedAmount,
                'estimated_hours' => $estimatedHours,
            ]);
            $current = $this->repairs->find($id);
            if (($current['status'] ?? '') === 'diagnosing') {
                $this->repairs->transition($id, 'waiting_customer_approval', (int) \Auth::id(), 'هزینه و زمان پیشنهادی برای تأیید مشتری ثبت شد.');
            }
            \set_flash('success', 'تشخیص و هزینه پیشنهادی ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت تشخیص انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function customerDecision($id)
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        $id = (int) $id;
        try {
            $this->repairs->decideCustomerApproval($id, (int) \Auth::id(), trim((string) ($_POST['decision'] ?? '')));
            \set_flash('success', 'تصمیم مشتری در پرونده ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت تصمیم مشتری انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function saveWarranty($id)
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        $id = (int) $id;
        try {
            $input = $_POST;
            foreach (['starts_at', 'ends_at'] as $dateKey) {
                $rawDate = trim((string) ($input[$dateKey] ?? ''));
                $input[$dateKey] = $rawDate === '' ? null : RepairDate::date($rawDate);
                if ($rawDate !== '' && !$input[$dateKey]) {
                    throw new \InvalidArgumentException('تاریخ گارانتی معتبر نیست.');
                }
            }
            $this->repairs->saveWarranty($id, (int) \Auth::id(), $input);
            \set_flash('success', 'گارانتی تعمیر ذخیره شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ذخیره گارانتی انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function addNote($id)
    {
        $this->onlyPost();
        $id = (int) $id;
        $this->assertAssignedWhenTechnician($id);
        $note = mb_substr(trim(strip_tags((string) ($_POST['note'] ?? ''))), 0, 3000, 'UTF-8');
        if ($note === '') {
            \set_flash('error', 'متن یادداشت را وارد کنید.');
        } else {
            $this->repairs->addNote($id, (int) \Auth::id(), $note, !empty($_POST['visible_to_customer']));
            \set_flash('success', 'یادداشت ثبت شد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function assign($id)
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        $id = (int) $id;
        try {
            $this->repairs->assign($id, (int) ($_POST['technician_user_id'] ?? 0), (int) \Auth::id(), trim((string) ($_POST['assignment_role'] ?? 'primary')));
            \set_flash('success', 'ارجاع تعمیرکار ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ارجاع تعمیرکار انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function deleteRepair($id)
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        if (!\PluginManager::can('plugin.proma-repair.manage_orders')) {
            \ErrorHandler::abort(403);
        }
        $id = (int) $id;
        $reason = mb_substr(trim(strip_tags((string) ($_POST['reason'] ?? ''))), 0, 255, 'UTF-8');
        try {
            if (!$this->repairs->find($id)) {
                \ErrorHandler::abort(404);
            }
            $this->repairs->softDelete($id, (int) \Auth::id(), $reason);
            \set_flash('success', 'پرونده از فهرست فعال حذف و برای حفظ سوابق مالی و ممیزی بایگانی شد.');
            \redirect('plugin/repair/orders');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'بایگانی پرونده انجام نشد.');
            \redirect('plugin/repair/detail/' . $id);
        }
    }

    public function addCharge($id)
    {
        $this->onlyPost();
        $this->assertNotTechnicianFinancial();
        $id = (int) $id;
        try {
            $this->repairs->addCharge($id, (int) \Auth::id(), $this->financialInput($_POST, 'charge'));
            \set_flash('success', 'مبلغ خدمات به پرونده اضافه شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت مبلغ انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function addPayment($id)
    {
        $this->onlyPost();
        $this->assertNotTechnicianFinancial();
        $id = (int) $id;
        try {
            $input = $this->financialInput($_POST, 'payment');
            $input['payment_type'] = in_array(($_POST['payment_type'] ?? ''), ['deposit', 'second', 'final', 'other'], true) ? $_POST['payment_type'] : 'deposit';
            $input['payment_method'] = in_array(($_POST['payment_method'] ?? ''), ['cash', 'card', 'transfer', 'pos', 'other'], true) ? $_POST['payment_method'] : 'cash';
            $input['reference'] = mb_substr(trim(strip_tags((string) ($_POST['reference'] ?? ''))), 0, 120, 'UTF-8');
            $input['note'] = mb_substr(trim(strip_tags((string) ($_POST['note'] ?? ''))), 0, 255, 'UTF-8');
            $paidAt = !empty($_POST['paid_date'])
                ? RepairDate::dateTime($_POST['paid_date'], $_POST['paid_time'] ?? '')
                : (trim((string) ($_POST['paid_at'] ?? '')) ?: date('Y-m-d H:i:s'));
            $paidAtTime = $paidAt !== '' ? strtotime($paidAt) : false;
            if ($paidAtTime === false || $paidAtTime > time() + 86400 || $paidAtTime < strtotime('-10 years')) {
                throw new \InvalidArgumentException('زمان پرداخت معتبر نیست.');
            }
            $input['paid_at'] = date('Y-m-d H:i:s', $paidAtTime);
            $this->repairs->addPayment($id, (int) \Auth::id(), $input);
            \set_flash('success', 'دریافت وجه ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت دریافت وجه انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function addExpense($id)
    {
        $this->onlyPost();
        $this->assertNotTechnicianFinancial();
        $id = (int) $id;
        try {
            $input = $this->financialInput($_POST, 'expense');
            $input['expense_type'] = in_array(($_POST['expense_type'] ?? ''), ['technician_cost', 'spare_part', 'transport', 'outsourcing', 'miscellaneous'], true) ? $_POST['expense_type'] : 'miscellaneous';
            $input['expense_date'] = RepairDate::date($_POST['expense_date'] ?? '') ?: date('Y-m-d');
            $input['technician_user_id'] = max(0, (int) ($_POST['technician_user_id'] ?? 0));
            $this->repairs->addExpense($id, (int) \Auth::id(), $input);
            \set_flash('success', 'هزینه تعمیر ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت هزینه انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function addTechnicianExpense($id)
    {
        $this->onlyPost();
        if (!RepairAuthorization::isTechnician()) {
            \ErrorHandler::abort(403);
        }
        $id = (int) $id;
        $this->assertAssignedWhenTechnician($id);
        try {
            $input = $this->financialInput($_POST, 'expense');
            $input['expense_type'] = in_array(($_POST['expense_type'] ?? ''), ['technician_cost', 'spare_part'], true) ? $_POST['expense_type'] : 'miscellaneous';
            if (!in_array($input['expense_type'], ['technician_cost', 'spare_part'], true)) {
                throw new \InvalidArgumentException('تعمیرکار فقط می‌تواند هزینه دستمزد یا قطعه ثبت کند.');
            }
            $input['technician_user_id'] = (int) \Auth::id();
            $input['expense_date'] = RepairDate::date($_POST['expense_date'] ?? '') ?: date('Y-m-d');
            $expenseId = $this->repairs->submitTechnicianExpense($id, (int) \Auth::id(), $input);
            \set_flash('success', 'هزینه برای تأیید مدیر ارسال شد. شماره درخواست: ' . $expenseId);
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت هزینه انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function approveTechnicianExpense($id, $expenseId)
    {
        $this->onlyPost();
        if (!\PluginManager::can('plugin.proma-repair.approve_expenses')) { \ErrorHandler::abort(403); }
        $id = (int) $id;
        try {
            $entryId = $this->repairs->approveTechnicianExpense($id, (int) $expenseId, (int) \Auth::id());
            \set_flash('success', 'هزینه تأیید شد و سند دفترکل حسابداری #' . $entryId . ' ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'تأیید هزینه انجام نشد. اتصال افزونه حسابداری را بررسی کنید.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function rejectTechnicianExpense($id, $expenseId)
    {
        $this->onlyPost();
        if (!\PluginManager::can('plugin.proma-repair.approve_expenses')) { \ErrorHandler::abort(403); }
        $id = (int) $id;
        try {
            $this->repairs->rejectTechnicianExpense($id, (int) $expenseId, (int) \Auth::id(), (string) ($_POST['manager_note'] ?? ''));
            \set_flash('success', 'درخواست هزینه با ثبت دلیل رد شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'رد درخواست هزینه انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function settleTechnicianExpense($id, $expenseId)
    {
        $this->onlyPost();
        if (!\PluginManager::can('plugin.proma-repair.settle_expenses')) { \ErrorHandler::abort(403); }
        $id = (int) $id;
        try {
            $entryId = $this->repairs->settleTechnicianExpense($id, (int) $expenseId, (int) \Auth::id());
            \set_flash('success', 'تسویه در دفترکل حسابداری #' . $entryId . ' ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'تسویه هزینه انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function voidFinancial($id, $kind, $entryId)
    {
        $this->onlyPost();
        $this->assertNotTechnicianFinancial();
        $id = (int) $id;
        $reason = mb_substr(trim(strip_tags((string) ($_POST['reason'] ?? ''))), 0, 255, 'UTF-8');
        try {
            if ($reason === '') {
                throw new \InvalidArgumentException('دلیل ابطال را وارد کنید.');
            }
            $this->repairs->voidFinancialEntry($id, (string) $kind, (int) $entryId, (int) \Auth::id(), $reason);
            \set_flash('success', 'سند مالی با حفظ سابقه باطل شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ابطال سند مالی انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id . '#financial');
    }

    public function revealPassword($id)
    {
        $this->onlyPost();
        $id = (int) $id;
        if (!RepairAuthorization::can('plugin.proma-repair.view_device_password')) {
            \ErrorHandler::abort(403);
        }
        $repair = $this->getAuthorizedRepair($id);
        if (!$repair) {
            \ErrorHandler::abort(404);
        }
        $secret = \Model::fetch('SELECT password_type, password_ciphertext FROM proma_repair_devices WHERE repair_id=? AND password_ciphertext IS NOT NULL AND password_deleted_at IS NULL LIMIT 1', [$id]);
        if ($secret) {
            $plain = (new \Proma\Plugins\Repair\Support\SecretCipher())->decrypt((string) $secret['password_ciphertext']);
            if (\Auth::ensureSessionWritable()) {
                try {
                    $this->repairs->audit($id, (int) \Auth::id(), 'device_password_viewed', ['ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'type' => $secret['password_type']]);
                    $_SESSION['repair_secret_reveals'][$id] = ['type' => $secret['password_type'], 'value' => $plain];
                } finally {
                    \Auth::commitSessionWrite();
                }
            }
        } else {
            \set_flash('error', 'رمز فعالی برای این دستگاه ثبت نشده است.');
        }
        \redirect('plugin/repair/detail/' . $id, $secret ? ['open' => 'repair-device-password-modal'] : []);
    }

    public function deleteDevicePassword($id)
    {
        $this->onlyPost();
        $id = (int) $id;
        if (RepairAuthorization::isTechnician() || !$this->getAuthorizedRepair($id)) {
            \ErrorHandler::abort(404);
        }
        try {
            if (!$this->repairs->deleteDevicePassword($id, (int) \Auth::id())) {
                throw new \InvalidArgumentException('رمزی برای حذف وجود ندارد.');
            }
            \set_flash('success', 'رمز دستگاه به‌صورت دائمی حذف شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'حذف رمز دستگاه انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function upload($id)
    {
        $this->onlyPost();
        $id = (int) $id;
        $this->assertAssignedWhenTechnician($id);
        try {
            $type = trim((string) ($_POST['attachment_type'] ?? 'damage_detail'));
            $types = ['front', 'back', 'left', 'right', 'damage_detail', 'repair_work'];
            if (!in_array($type, $types, true)) {
                throw new \InvalidArgumentException('نوع تصویر معتبر نیست.');
            }
            $stored = (new AttachmentService())->store($_FILES['image'] ?? [], $id);
            $this->repairs->storeAttachment($id, $type, $stored['path'], ['name' => $_FILES['image']['name'] ?? '', 'mime' => $stored['mime'], 'size' => $stored['size']], (int) \Auth::id());
            \set_flash('success', 'تصویر به پرونده پیوست شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'بارگذاری تصویر انجام نشد.');
        }
        \redirect('plugin/repair/detail/' . $id);
    }

    public function attachment($attachmentId)
    {
        $attachment = $this->repairs->attachment((int) $attachmentId);
        if (!$attachment) {
            \ErrorHandler::abort(404);
        }
        $this->assertAssignedWhenTechnician((int) $attachment['repair_id']);
        $this->streamAttachment($attachment);
    }

    private function streamAttachment(?array $attachment): void
    {
        if (!$attachment || !in_array((string) $attachment['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            \ErrorHandler::abort(404);
        }
        $path = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $attachment['stored_path']);
        $realBase = realpath(dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'promarepair');
        $realPath = realpath($path);
        if (!$realPath || !$realBase || strpos($realPath, $realBase . DIRECTORY_SEPARATOR) !== 0) {
            \ErrorHandler::abort(404);
        }
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . (int) filesize($realPath));
        header('Content-Disposition: inline; filename="repair-image"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($realPath);
        exit;
    }

    public function technicians()
    {
        $this->assertNotRepairTechnician();
        $users = \User::all('repair_technician', trim((string) ($_GET['q'] ?? '')), 'active', ['limit' => 80]);
        $this->render('plugin:proma-repair/technicians/index', ['title' => 'تعمیرکاران', 'technicians' => $this->repairs->technicians(), 'users' => $users]);
    }

    public function saveTechnician()
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        try {
            if (!empty($_POST['create_technician_account'])) {
                $this->repairs->createTechnicianAccount($_POST, (int) \Auth::id());
                \set_flash('success', 'حساب کاربری تعمیرکار ساخته و به فهرست تعمیرکاران اضافه شد.');
            } elseif (!empty($_POST['profile_user_id'])) {
                $this->repairs->saveTechnicianProfile((int) $_POST['profile_user_id'], (int) \Auth::id(), $_POST);
                \set_flash('success', 'پروفایل تعمیرکار به‌روزرسانی شد.');
            } else {
                $this->repairs->addTechnician((int) ($_POST['user_id'] ?? 0), (int) \Auth::id());
                \set_flash('success', 'کاربر به‌عنوان تعمیرکار ثبت شد.');
            }
        } catch (\InvalidArgumentException $e) {
            \ErrorHandler::abort(422, $e->getMessage());
        } catch (\Throwable $e) {
            \set_flash('error', 'ثبت تعمیرکار انجام نشد.');
        }
        \redirect('plugin/repair/technicians');
    }

    public function settings()
    {
        $this->assertNotRepairTechnician();
        $settings = (new RepairSettingsRepository())->all();
        $this->render('plugin:proma-repair/settings/index', ['title' => 'تنظیمات تعمیرات', 'settings' => $settings]);
    }

    public function saveSettings()
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        try {
            (new RepairSettingsRepository())->save($_POST, (int) \Auth::id());
            \AuditLog::record('repair', 'repair_settings_changed', 'plugin', 0, ['actor_user_id' => \Auth::id(), 'description' => 'تنظیمات مدیریت تعمیرات تغییر کرد.']);
            \set_flash('success', 'تنظیمات تعمیرات ذخیره شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ذخیره تنظیمات تعمیرات انجام نشد.');
        }
        \redirect('plugin/repair/settings');
    }

    public function permissions()
    {
        $this->assertNotRepairTechnician();
        $keys = $this->permissionKeys();
        $rows = \Model::fetchAll('SELECT role, permission_key, is_allowed FROM system_plugin_role_permissions WHERE plugin_id=?', ['proma-repair']);
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['role']][$row['permission_key']] = (int) $row['is_allowed'] === 1;
        }
        $this->render('plugin:proma-repair/settings/permissions', [
            'title' => 'مجوزهای تعمیرات', 'permissions' => $keys, 'matrix' => $matrix,
            'roles' => ['operator' => 'اپراتور', 'repair_technician' => 'تعمیرکار', 'lawyer' => 'واحد حقوقی'],
        ]);
    }

    public function savePermissions()
    {
        $this->onlyPost();
        $this->assertNotRepairTechnician();
        $permissions = (array) ($_POST['permissions'] ?? []);
        $roles = ['operator', 'repair_technician', 'lawyer'];
        \Model::begin();
        try {
            foreach ($roles as $role) {
                foreach (array_keys($this->permissionKeys()) as $key) {
                    $allowed = !empty($permissions[$role][$key]) ? 1 : 0;
                    \Model::execute(
                        'INSERT INTO system_plugin_role_permissions (plugin_id, permission_key, role, is_allowed, granted_by, updated_at)
                         VALUES (?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed), granted_by=VALUES(granted_by), updated_at=NOW()',
                        ['proma-repair', $key, $role, $allowed, (int) \Auth::id()]
                    );
                }
            }
            \Model::commit();
            \AuditLog::record('repair', 'repair_permissions_changed', 'plugin', 0, ['actor_user_id' => \Auth::id(), 'description' => 'مجوزهای نقش‌های تعمیرات تغییر کرد.']);
            \set_flash('success', 'مجوزهای نقش‌ها ذخیره شد.');
        } catch (\Throwable $e) {
            \Model::rollBack();
            \set_flash('error', 'ذخیره مجوزهای نقش‌ها انجام نشد.');
        }
        \redirect('plugin/repair/permissions');
    }

    public function reports()
    {
        $this->assertNotTechnicianFinancial();
        $this->render('plugin:proma-repair/reports/index', [
            'title' => 'گزارش تعمیرات', 'reports' => $this->repairs->statistics(),
            'canProfit' => \PluginManager::can('plugin.proma-repair.view_profit'),
        ]);
    }

    public function audit()
    {
        $this->assertNotRepairTechnician();
        $this->render('plugin:proma-repair/audit/index', ['title' => 'گزارش ممیزی تعمیرات', 'events' => $this->repairs->auditRows()]);
    }

    public function receipt($id)
    {
        $repair = $this->getAuthorizedRepair((int) $id);
        if (!$repair) {
            \ErrorHandler::abort(404);
        }
        $this->repairs->audit((int) $id, (int) \Auth::id(), 'receipt_printed');
        $this->render('plugin:proma-repair/receipt/show', [
            'title' => 'رسید پذیرش تعمیر', 'repair' => $repair,
            'settings' => (new RepairSettingsRepository())->all(),
            'financial' => \PluginManager::can('plugin.proma-repair.view_financial') ? $this->repairs->financialSummary((int) $id) : null,
            'warranty' => \Model::fetch('SELECT * FROM proma_repair_warranties WHERE repair_id=? LIMIT 1', [(int) $id]),
            'timeline' => $this->repairs->timeline((int) $id),
        ], null);
    }

    public function customerRepairs()
    {
        $this->requireCustomer();
        $settings = (new RepairSettingsRepository())->all();
        $items = $this->repairs->customerList((int) \Auth::id(), true, $settings['show_customer_delivery_estimate'] === '1');
        $this->render('plugin:proma-repair/customer/index', ['title' => 'تعمیرات من', 'items' => $items, 'settings' => $settings]);
    }

    public function customerDetail($id)
    {
        $this->requireCustomer();
        $settings = (new RepairSettingsRepository())->all();
        $repair = $this->repairs->customerDetail((int) $id, (int) \Auth::id(), true, $settings['show_customer_delivery_estimate'] === '1', $settings['show_customer_timeline'] === '1', $settings['show_customer_warranty'] === '1');
        if (!$repair) {
            \ErrorHandler::abort(404);
        }
        $this->render('plugin:proma-repair/customer/detail', [
            'title' => 'جزئیات تعمیر', 'repair' => $repair, 'settings' => $settings,
        ]);
    }

    public function customerRespond($id)
    {
        $this->requireCustomer();
        $this->onlyPost();
        $id = (int) $id;
        if (!$this->repairs->customerDetail($id, (int) \Auth::id())) {
            \ErrorHandler::abort(404);
        }
        $decision = trim((string) ($_POST['decision'] ?? ''));
        try {
            if (($this->repairs->find($id)['status'] ?? '') !== 'waiting_customer_approval') {
                throw new \InvalidArgumentException('این پرونده در انتظار تأیید هزینه نیست.');
            }
            $this->repairs->decideCustomerApproval($id, (int) \Auth::id(), $decision);
            \set_flash('success', $decision === 'approved' ? 'تأیید هزینه ثبت شد.' : 'رد هزینه ثبت شد. برای ادامه با مرکز تعمیر تماس بگیرید.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت تصمیم انجام نشد.');
        }
        \redirect('portal/repairs/' . $id);
    }

    public function customerImage($attachmentId)
    {
        $this->requireCustomer();
        $attachment = $this->repairs->customerAttachment((int) $attachmentId, (int) \Auth::id());
        $this->streamAttachment($attachment);
    }

    public function tracking()
    {
        $this->render('plugin:proma-repair/tracking/index', ['title' => 'پیگیری تعمیر', 'trackingCode' => trim((string) ($_GET['code'] ?? ''))]);
    }

    public function trackLookup()
    {
        $this->onlyPost();
        try {
            $repair = $this->service->track((string) ($_POST['tracking_code'] ?? ''), (string) ($_POST['mobile'] ?? ''), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            if (!$repair) {
                throw new \InvalidArgumentException('کد رهگیری یا شماره موبایل درست نیست.');
            }
            $this->render('plugin:proma-repair/tracking/result', ['title' => 'نتیجه پیگیری تعمیر', 'repair' => $repair], null);
        } catch (\Throwable $e) {
            $this->render('plugin:proma-repair/tracking/index', [
                'title' => 'پیگیری تعمیر', 'errorMessage' => $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException ? $e->getMessage() : 'پیگیری تعمیر انجام نشد.',
                'trackingCode' => trim((string) ($_POST['tracking_code'] ?? '')),
            ]);
        }
    }

    private function getAuthorizedRepair(int $id): ?array
    {
        $repair = $this->repairs->find($id);
        if (!$repair) {
            return null;
        }
        $this->assertAssignedWhenTechnician($id);
        return $repair;
    }

    private function assertAssignedWhenTechnician(int $id): void
    {
        if ($this->isRestrictedTechnician() && !$this->repairs->isAssigned($id, (int) \Auth::id())) {
            \ErrorHandler::abort(404);
        }
    }

    private function assertNotTechnicianFinancial(): void
    {
        if (RepairAuthorization::isTechnician()) {
            \ErrorHandler::abort(403);
        }
    }

    private function assertTechnician(): void
    {
        if (!RepairAuthorization::isTechnician() || !$this->repairs->technicianProfile((int) \Auth::id())) {
            \ErrorHandler::abort(403);
        }
    }

    private function assertNotRepairTechnician(): void
    {
        if (RepairAuthorization::isTechnician()) {
            \ErrorHandler::abort(403);
        }
    }

    private function isRestrictedTechnician(): bool
    {
        if (RepairAuthorization::isTechnician()) {
            return true;
        }
        if (RepairAuthorization::can('plugin.proma-repair.manage_orders')) {
            return false;
        }
        return (bool) \Model::fetch('SELECT id FROM proma_repair_technicians WHERE user_id=? AND active=1 AND role_active=1 LIMIT 1', [(int) \Auth::id()]);
    }

    private function requireCustomer(): void
    {
        \Auth::requireLogin();
        if ((string) \Auth::role() !== 'customer') {
            \ErrorHandler::abort(403);
        }
    }

    private function financialInput(array $input, string $kind): array
    {
        $amount = RepairMoney::input($input['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ باید بیشتر از صفر و در محدوده مجاز باشد.');
        }
        $description = mb_substr(trim(strip_tags((string) ($input['description'] ?? ''))), 0, 255, 'UTF-8');
        if ($description === '') {
            $description = $kind === 'expense' ? 'هزینه تعمیر' : 'خدمات تعمیر';
        }
        return [
            'amount' => $amount, 'description' => $description,
            'charge_type' => in_array(($input['charge_type'] ?? ''), ['repair_service', 'spare_parts', 'additional_service', 'delivery', 'miscellaneous'], true) ? $input['charge_type'] : 'repair_service',
        ];
    }

    private function statusLabels(): array
    {
        $labels = [];
        foreach (RepairWorkflow::STATUSES as $status) {
            $labels[$status] = RepairWorkflow::label($status);
        }
        return $labels;
    }

    private function financialDashboard(array $finance): array
    {
        $revenue = RepairMoney::stored($finance['revenue'] ?? 0);
        $received = RepairMoney::stored($finance['received'] ?? 0);
        $costs = RepairMoney::stored($finance['costs'] ?? 0);
        $parts = RepairMoney::stored($finance['part_costs'] ?? 0);
        return [
            'revenue' => $revenue, 'received' => $received, 'balance' => max(0, $revenue - $received),
            'costs' => $costs, 'part_costs' => $parts, 'technician_costs' => RepairMoney::stored($finance['technician_costs'] ?? 0),
            'other_costs' => RepairMoney::stored($finance['other_costs'] ?? 0), 'gross_profit' => $revenue - $parts, 'profit' => $revenue - $costs,
        ];
    }

    private function permissionKeys(): array
    {
        return [
            'plugin.proma-repair.view_orders' => 'مشاهده تعمیرات',
            'plugin.proma-repair.create_orders' => 'ثبت تعمیر جدید',
            'plugin.proma-repair.manage_orders' => 'ویرایش تعمیرات و ثبت تشخیص',
            'plugin.proma-repair.change_status' => 'تغییر وضعیت تعمیر',
            'plugin.proma-repair.assign_technicians' => 'ارجاع تعمیرکار',
            'plugin.proma-repair.manage_technicians' => 'مدیریت تعمیرکاران',
            'plugin.proma-repair.view_financial' => 'مشاهده اطلاعات مالی تعمیرات',
            'plugin.proma-repair.manage_financial' => 'ثبت پرداخت، هزینه و مبلغ خدمات',
            'plugin.proma-repair.record_costs' => 'ثبت هزینه قطعه و دستمزد برای پرونده ارجاع‌شده',
            'plugin.proma-repair.approve_expenses' => 'تأیید هزینه‌های ثبت‌شده تعمیرکاران',
            'plugin.proma-repair.settle_expenses' => 'تسویه هزینه‌های تأییدشده تعمیرکاران',
            'plugin.proma-repair.view_profit' => 'مشاهده سود تعمیرات',
            'plugin.proma-repair.view_device_password' => 'مشاهده رمز دستگاه',
            'plugin.proma-repair.delete_device_password' => 'حذف دستی رمز دستگاه',
            'plugin.proma-repair.upload_attachments' => 'بارگذاری تصاویر',
            'plugin.proma-repair.print_receipt' => 'چاپ رسید تعمیر',
            'plugin.proma-repair.manage_warranty' => 'مدیریت گارانتی تعمیر',
            'plugin.proma-repair.manage_settings' => 'مدیریت تنظیمات و مجوزها',
            'plugin.proma-repair.view_audit' => 'مشاهده گزارش ممیزی',
        ];
    }
}
