<?php

namespace Proma\Plugins\InventoryPartners\Controllers;

use Proma\Plugins\InventoryPartners\Domain\Money;
use Proma\Plugins\InventoryPartners\Services\CatalogService;
use Proma\Plugins\InventoryPartners\Services\ExpenseService;
use Proma\Plugins\InventoryPartners\Services\PartnerService;
use Proma\Plugins\InventoryPartners\Services\ProductService;
use Proma\Plugins\InventoryPartners\Services\PurchaseService;
use Proma\Plugins\InventoryPartners\Services\ReportingService;
use Proma\Plugins\InventoryPartners\Services\SaleService;
use Proma\Plugins\InventoryPartners\Services\SettlementService;
use Proma\Plugins\InventoryPartners\Services\StockService;

final class InventoryPartnersController extends \Controller
{
    private const BASE = 'plugin/inventory-partners/';
    private const COST_PERMISSION = 'plugin.proma-inventory-partners.view_costs';

    public function dashboard()
    {
        $costs = $this->canViewCosts();
        $this->page('dashboard', 'داشبورد انبار', [
            'summary' => ReportingService::dashboard($costs),
            'recentPurchases' => ReportingService::purchases($costs),
            'recentSales' => ReportingService::sales($costs),
            'recentSettlements' => ReportingService::settlements($costs),
            'lowProducts' => $this->lowStockProducts(),
            'canCosts' => $costs,
        ]);
    }

    public function products()
    {
        $costs = $this->canViewCosts();
        $this->page('products', 'مدیریت کالاها', [
            'products' => ReportingService::products($_GET['q'] ?? '', $costs),
            'query' => trim((string) ($_GET['q'] ?? '')),
            'canCosts' => $costs,
        ]);
    }

    public function createProduct()
    {
        $this->post('products', function () {
            ProductService::create($_POST, \Auth::id());
        }, 'کالا با موفقیت ثبت شد.');
    }

    public function updateProduct()
    {
        $this->post('products', function () {
            ProductService::update((int) ($_POST['product_id'] ?? 0), $_POST, \Auth::id());
        }, 'اطلاعات کالا به‌روزرسانی شد.');
    }

    public function updatePrices()
    {
        $this->post('pricing', function () {
            ProductService::updatePrices((int) ($_POST['product_id'] ?? 0), $_POST, $_POST['reason'] ?? '', \Auth::id());
        }, 'تغییر قیمت در تاریخچه ثبت شد.');
    }

    public function purchases()
    {
        $costs = $this->canViewCosts();
        $this->page('purchases', 'ثبت خرید و ورود کالا', [
            'purchases' => ReportingService::purchases($costs),
            'warehouses' => ReportingService::activeWarehouses(),
            'suppliers' => ReportingService::suppliers(),
            'partners' => \Proma\Plugins\InventoryPartners\Services\OwnershipService::activePartners(),
            'canCosts' => $costs,
        ]);
    }

    public function createPurchase()
    {
        $this->post('purchases', function () {
            $data = $_POST;
            $data['purchase_date'] = $this->dateValue($data['purchase_date'] ?? '') ?: date('Y-m-d');
            PurchaseService::create($data, \Auth::id());
        }, 'خرید، محموله و گردش موجودی ثبت شد.');
    }

    public function stock()
    {
        $costs = $this->canViewCosts();
        $this->page('stock', 'موجودی انبار', [
            'lots' => StockService::lots($costs, (int) ($_GET['warehouse_id'] ?? 0)),
            'movements' => StockService::movements(120, $costs),
            'warehouses' => ReportingService::activeWarehouses(),
            'partners' => \Proma\Plugins\InventoryPartners\Services\OwnershipService::activePartners(),
            'canCosts' => $costs,
        ]);
    }

    public function adjustStock()
    {
        $this->post('stock', function () {
            StockService::adjust($_POST, \Auth::id());
        }, 'سند تعدیل و گردش موجودی ثبت شد.');
    }

    public function pricing()
    {
        $costs = $this->canViewCosts();
        $this->page('pricing', 'قیمت‌گذاری', [
            'products' => ReportingService::products('', $costs),
            'history' => $costs ? ReportingService::priceHistory((int) ($_GET['product_id'] ?? 0)) : [],
            'canCosts' => $costs,
        ]);
    }

    public function sales()
    {
        $costs = $this->canViewCosts();
        $this->page('sales', 'فروش کالا', [
            'sales' => ReportingService::sales($costs),
            'lots' => StockService::lots(false),
            'warehouses' => ReportingService::activeWarehouses(),
            'canCosts' => $costs,
        ]);
    }

    public function createCashSale()
    {
        $this->post('sales', function () {
            SaleService::create($_POST, \Auth::id(), 'cash');
        }, 'فروش نقدی، خروج کالا و سند مالی ثبت شد.');
    }

    public function createContractSale()
    {
        $this->post('sales', function () {
            SaleService::create($_POST, \Auth::id(), 'installment');
        }, 'فروش به قرارداد متصل شد. پرداخت‌های قطعی قرارداد برای تخصیص وجه تطبیق شدند.');
    }

    public function reconcileSale($saleId)
    {
        $this->post('sales', function () use ($saleId) {
            if (SaleService::reconcileSale((int) $saleId, \Auth::id()) < 0) {
                throw new \RuntimeException('تطبیق پرداخت‌ها انجام نشد.');
            }
        }, 'وضعیت پرداخت‌های قطعی قرارداد تطبیق شد.');
    }

    public function returnSale($saleId)
    {
        $this->post('sales', function () use ($saleId) {
            SaleService::returnFull((int) $saleId, $_POST['reason'] ?? '', $_POST['refund_reference'] ?? '', \Auth::id());
        }, 'برگشت کامل فروش و اسناد معکوس ثبت شد.');
    }

    public function partners()
    {
        $costs = $this->canViewCosts();
        $this->page('partners', 'شرکا و سرمایه‌ها', [
            'partners' => ReportingService::partners($costs),
            'canCosts' => $costs,
        ]);
    }

    public function createPartner()
    {
        $this->post('partners', function () {
            $data = $_POST;
            $data['started_on'] = $this->dateValue($data['started_on'] ?? '');
            PartnerService::create($data, \Auth::id());
        }, 'شریک جدید ثبت شد.');
    }

    public function recordCapital()
    {
        $this->post('partners', function () {
            $amount = Money::fromToman($_POST['amount_toman'] ?? '0');
            PartnerService::recordCapital(
                (int) ($_POST['partner_id'] ?? 0),
                $amount,
                $_POST['method'] ?? '',
                $_POST['reference_no'] ?? '',
                \Auth::id(),
                $_POST['idempotency_key'] ?? '',
                $_POST['note'] ?? ''
            );
        }, 'آوردهٔ سرمایه با سند دوطرفه ثبت شد.');
    }

    public function partnerLedgers()
    {
        \PluginManager::requirePermission(self::COST_PERMISSION);
        $this->page('ledger', 'دفتر حساب شرکا', [
            'partners' => ReportingService::partners(true),
            'selectedPartner' => null,
            'entries' => [],
        ]);
    }

    public function partnerLedger($partnerId)
    {
        \PluginManager::requirePermission(self::COST_PERMISSION);
        $partnerId = (int) $partnerId;
        $this->page('ledger', 'دفتر حساب شریک', [
            'partners' => ReportingService::partners(true),
            'selectedPartner' => PartnerService::summary($partnerId),
            'entries' => ReportingService::partnerLedger($partnerId),
        ]);
    }

    public function settlements()
    {
        $costs = $this->canViewCosts();
        $this->page('settlements', 'تسویه‌حساب شرکا', [
            'settlements' => ReportingService::settlements($costs),
            'partners' => ReportingService::partners($costs),
            'canCosts' => $costs,
        ]);
    }

    public function requestSettlement()
    {
        $this->post('settlements', function () {
            SettlementService::request(
                (int) ($_POST['partner_id'] ?? 0),
                Money::fromToman($_POST['amount_toman'] ?? '0'),
                $_POST['reason'] ?? '',
                \Auth::id(),
                $_POST['idempotency_key'] ?? ''
            );
        }, 'درخواست تسویه برای بررسی ثبت شد.');
    }

    public function approveSettlement($settlementId)
    {
        $this->post('settlements', function () use ($settlementId) {
            SettlementService::approve((int) $settlementId, \Auth::id());
        }, 'درخواست تسویه تأیید شد.');
    }

    public function rejectSettlement($settlementId)
    {
        $this->post('settlements', function () use ($settlementId) {
            SettlementService::reject((int) $settlementId, \Auth::id(), $_POST['reason'] ?? '');
        }, 'درخواست تسویه رد شد.');
    }

    public function paySettlement($settlementId)
    {
        $this->post('settlements', function () use ($settlementId) {
            SettlementService::pay((int) $settlementId, $_POST['method'] ?? '', $_POST['reference'] ?? '', \Auth::id());
        }, 'پرداخت تسویه با مرجع رسید ثبت شد.');
    }

    public function reverseSettlement($settlementId)
    {
        $this->post('settlements', function () use ($settlementId) {
            SettlementService::reverse((int) $settlementId, $_POST['reason'] ?? '', \Auth::id());
        }, 'سند پرداخت تسویه با سند معکوس اصلاح شد.');
    }

    public function expenses()
    {
        $rows = ReportingService::expenses();
        $expenseIds = array_map(static function ($row) { return (int) $row['id']; }, $rows);
        $this->page('expenses', 'هزینه‌های شراکت', [
            'expenses' => $rows,
            'expenseAllocations' => ReportingService::expenseAllocations($expenseIds),
            'partners' => \Proma\Plugins\InventoryPartners\Services\OwnershipService::activePartners(),
            'canCosts' => $this->canViewCosts(),
        ]);
    }

    public function createExpense()
    {
        $this->post('expenses', function () {
            $data = $_POST;
            $data['expense_date'] = $this->dateValue($data['expense_date'] ?? '') ?: date('Y-m-d');
            ExpenseService::create($data, \Auth::id());
        }, 'هزینه و سهم هر شریک با سند مالی ثبت شد.');
    }

    public function transfers()
    {
        $this->page('transfers', 'انتقال موجودی', [
            'transfers' => ReportingService::transfers(),
            'lots' => StockService::lots(false),
            'warehouses' => ReportingService::activeWarehouses(),
        ]);
    }

    public function createTransfer()
    {
        $this->post('transfers', function () {
            StockService::createTransfer($_POST, \Auth::id());
        }, 'انتقال موجودی رزرو شد و منتظر تأیید دریافت است.');
    }

    public function receiveTransfer($transferId)
    {
        $this->post('transfers', function () use ($transferId) {
            StockService::receiveTransfer((int) $transferId, \Auth::id());
        }, 'دریافت انتقال ثبت شد.');
    }

    public function cancelTransfer($transferId)
    {
        $this->post('transfers', function () use ($transferId) {
            StockService::cancelTransfer((int) $transferId, $_POST['reason'] ?? '', \Auth::id());
        }, 'انتقال لغو و رزرو موجودی آزاد شد.');
    }

    public function profitLoss()
    {
        \PluginManager::requirePermission(self::COST_PERMISSION);
        $range = $this->reportRange($_GET, ReportingService::settings());
        $this->page('profit-loss', 'گزارش سود و زیان', [
            'result' => ReportingService::profitLoss($range['from'], $range['to']),
            'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '',
            'period' => $range['period'], 'year' => $range['year'],
            'fiscalMonth' => $range['fiscal_month'], 'fiscalDay' => $range['fiscal_day'],
            'periodStart' => $range['from'], 'periodEnd' => $range['to'],
        ]);
    }

    public function reports()
    {
        $costs = $this->canViewCosts();
        $this->page('reports', 'گزارش‌ها', [
            'summary' => ReportingService::dashboard($costs),
            'lots' => StockService::lots($costs),
            'sales' => ReportingService::sales($costs),
            'canCosts' => $costs,
        ]);
    }

    public function exportReport()
    {
        $type = trim((string) ($_GET['type'] ?? 'stock'));
        $costs = $this->canViewCosts();
        if (in_array($type, ['sales', 'profit-loss', 'partners', 'settlements', 'prices', 'activity'], true) && !$costs) {
            \PluginManager::requirePermission(self::COST_PERMISSION);
        }
        if ($type === 'activity') {
            \PluginManager::requirePermission('plugin.proma-inventory-partners.view_audit');
        }
        $rows = [];
        switch ($type) {
            case 'products':
                $rows = ReportingService::products('', $costs);
                break;
            case 'sales':
                $rows = ReportingService::sales($costs);
                break;
            case 'partners':
                $rows = ReportingService::partners($costs);
                break;
            case 'settlements':
                $rows = ReportingService::settlements($costs);
                break;
            case 'expenses':
                \PluginManager::requirePermission('plugin.proma-inventory-partners.view_expenses');
                $range = $this->reportRange($_GET, ReportingService::settings());
                $rows = ReportingService::expenses($range['from'], $range['to']);
                break;
            case 'prices':
                $rows = ReportingService::priceHistory();
                break;
            case 'activity':
                $rows = ReportingService::activity();
                break;
            case 'profit-loss':
                $range = $this->reportRange($_GET, ReportingService::settings());
                $rows = [ReportingService::profitLoss($range['from'], $range['to'])];
                break;
            case 'stock':
            default:
                $rows = StockService::lots($costs, (int) ($_GET['warehouse_id'] ?? 0));
                break;
        }
        $this->sendCsv('inventory-' . $type . '-' . date('Ymd') . '.csv', $rows);
    }

    public function activity()
    {
        \PluginManager::requirePermission(self::COST_PERMISSION);
        $this->page('activity', 'تاریخچه فعالیت‌ها', [
            'activityRows' => ReportingService::activity(),
        ]);
    }

    public function settings()
    {
        $this->page('settings', 'تنظیمات انبار و شرکا', [
            'settings' => ReportingService::settings(),
            'warehouses' => ReportingService::warehouses(),
            'suppliers' => ReportingService::suppliers(),
            'brands' => CatalogService::active('brand'),
            'categories' => CatalogService::active('category'),
        ]);
    }

    public function saveSettings()
    {
        $this->post('settings', function () {
            $allowed = [
                'currency_display' => ['toman', 'rial'],
                'cost_method' => ['lot_specific'],
                'receipts_policy' => ['capital_first'],
                'rounding_policy' => ['largest_remainder_partner_id_tiebreak'],
                'sku_pattern' => null,
                'fiscal_start_month' => null,
                'fiscal_start_day' => null,
                'default_warehouse_id' => null,
            ];
            foreach ($allowed as $key => $choices) {
                if (!array_key_exists($key, $_POST)) { continue; }
                $value = trim((string) $_POST[$key]);
                if ($key === 'sku_pattern') {
                    $value = ProductService::validateSkuPattern($value);
                }
                if ($key === 'fiscal_start_month') {
                    $month = Money::quantity($value);
                    if ($month < 1 || $month > 12) { throw new \InvalidArgumentException('ماه آغاز سال مالی باید بین ۱ تا ۱۲ باشد.'); }
                    $value = (string) $month;
                }
                if ($key === 'fiscal_start_day') {
                    $day = Money::quantity($value);
                    $month = Money::quantity($_POST['fiscal_start_month'] ?? ReportingService::settings()['fiscal_start_month'] ?? '1');
                    $maxDay = $month <= 6 ? 31 : ($month === 12 ? 29 : 30);
                    if ($day < 1 || $day > $maxDay) { throw new \InvalidArgumentException('روز آغاز سال مالی با ماه انتخاب‌شده معتبر نیست.'); }
                    $value = (string) $day;
                }
                if ($choices !== null && !in_array($value, $choices, true)) {
                    throw new \InvalidArgumentException('مقدار تنظیم انتخاب‌شده معتبر نیست.');
                }
                if ($key === 'default_warehouse_id'
                    && !\Model::fetch("SELECT id FROM plugin_inventory_warehouses WHERE id = ? AND status = 'active' LIMIT 1", [(int) $value])) {
                    throw new \InvalidArgumentException('انبار پیش‌فرض فعال پیدا نشد.');
                }
                \Model::execute('INSERT INTO plugin_inventory_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()', [
                    $key, $value, \Auth::id() ? (int) \Auth::id() : null,
                ]);
            }
            \Proma\Plugins\InventoryPartners\Services\AuditService::record('inventory_settings_updated', 'settings', null, \Auth::id(), [], ['keys' => array_values(array_intersect(array_keys($allowed), array_keys($_POST)))]);
        }, 'تنظیمات افزونه ذخیره شد.');
    }

    public function createWarehouse()
    {
        $this->post('settings', function () {
            ProductService::createWarehouse($_POST, \Auth::id());
        }, 'انبار جدید ایجاد شد.');
    }

    public function createSupplier()
    {
        $this->post('settings', function () {
            ProductService::createSupplier($_POST, \Auth::id());
        }, 'تأمین‌کننده جدید ثبت شد.');
    }

    public function createBrand()
    {
        $this->post('settings', function () {
            CatalogService::create('brand', $_POST['name'] ?? '', \Auth::id());
        }, 'برند در فهرست مرکزی ثبت شد.');
    }

    public function createCategory()
    {
        $this->post('settings', function () {
            CatalogService::create('category', $_POST['name'] ?? '', \Auth::id());
        }, 'دسته‌بندی در فهرست مرکزی ثبت شد.');
    }

    public function apiDashboard()
    {
        $costs = $this->canViewCosts();
        $this->json(['ok' => true, 'data' => ReportingService::dashboard($costs)]);
    }

    public function apiProducts()
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $this->json(['ok' => true, 'data' => ReportingService::searchActiveProducts($query)]);
    }

    public function apiBrands()
    {
        $this->json(['ok' => true, 'data' => CatalogService::search('brand', $_GET['q'] ?? '', 30)]);
    }

    public function apiCategories()
    {
        $this->json(['ok' => true, 'data' => CatalogService::search('category', $_GET['q'] ?? '', 30)]);
    }

    public function apiContracts()
    {
        $this->json(['ok' => true, 'data' => ReportingService::searchAvailableContracts($_GET['q'] ?? '')]);
    }

    private function page($module, $title, array $data = [])
    {
        $this->render('plugin:proma-inventory-partners/page', array_merge($data, [
            'title' => $title,
            'module' => $module,
            'can' => static function ($permission) {
                return \PluginManager::can('plugin.proma-inventory-partners.' . ltrim((string) $permission, '.'));
            },
            'canCosts' => $data['canCosts'] ?? $this->canViewCosts(),
            'currencyDisplay' => ReportingService::settings()['currency_display'] ?? 'toman',
            'nonce' => bin2hex(random_bytes(20)),
        ]));
    }

    private function canViewCosts()
    {
        return \PluginManager::can(self::COST_PERMISSION);
    }

    private function post($destination, callable $operation, $success)
    {
        $this->onlyPost();
        try {
            $operation();
            \set_flash('success', $success);
        } catch (\InvalidArgumentException $error) {
            \set_flash('error', $error->getMessage());
        } catch (\Throwable $error) {
            \ErrorHandler::log('inventory_partners_action', $error, 500);
            \set_flash('error', 'عملیات انجام نشد. شناسه پیگیری برای بررسی خطا ثبت شد.');
        }
        \redirect(self::BASE . $destination);
    }

    private function dateValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') { return ''; }
        return \parse_jalali_date($value) ?: '';
    }

    private function reportRange(array $query, array $settings)
    {
        $period = trim((string) ($query['period'] ?? 'range'));
        if (!in_array($period, ['range', 'solar', 'fiscal'], true)) { $period = 'range'; }
        $yearDigits = $this->asciiDigits((string) ($query['year'] ?? ''));
        if ($yearDigits === '') {
            $today = function_exists('jdatetime') ? (string) \jdatetime(date('Y-m-d')) : date('Y-m-d');
            preg_match('/(1[0-9]{3})/', $this->asciiDigits($today), $match);
            $yearDigits = $match[1] ?? date('Y');
        }
        $year = max(1300, min(1500, (int) $yearDigits));
        $month = $period === 'fiscal' ? max(1, min(12, (int) ($settings['fiscal_start_month'] ?? 1))) : 1;
        $maxFiscalDay = $month <= 6 ? 31 : ($month === 12 ? 29 : 30);
        $day = $period === 'fiscal' ? max(1, min($maxFiscalDay, (int) ($settings['fiscal_start_day'] ?? 1))) : 1;
        if ($period !== 'range') {
            $from = $this->jalaliBoundary($year, $month, $day);
            $next = $this->jalaliBoundary($year + 1, $month, $day);
            $toDate = new \DateTime($next);
            $toDate->modify('-1 day');
            $to = $toDate->format('Y-m-d');
        } else {
            $from = $this->dateValue($query['from'] ?? '');
            $to = $this->dateValue($query['to'] ?? '');
            if ($from !== '' && $to !== '' && $from > $to) {
                [$from, $to] = [$to, $from];
            }
        }
        return ['period' => $period, 'year' => $year, 'from' => $from, 'to' => $to, 'fiscal_month' => $month, 'fiscal_day' => $day];
    }

    private function jalaliBoundary($year, $month, $day)
    {
        for ($candidate = min(31, (int) $day); $candidate >= 1; $candidate--) {
            $date = \parse_jalali_date(sprintf('%04d/%02d/%02d', (int) $year, (int) $month, $candidate));
            if ($date) { return $date; }
        }
        throw new \InvalidArgumentException('آغاز دورهٔ مالی با تقویم شمسی سازگار نیست.');
    }

    private function asciiDigits($value)
    {
        return strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function lowStockProducts()
    {
        return \Model::fetchAll('SELECT p.id, p.sku, p.name, p.minimum_stock, COALESCE(SUM(l.on_hand_qty - l.reserved_qty),0) AS available_qty
            FROM plugin_inventory_products p LEFT JOIN plugin_inventory_stock_lots l ON l.product_id = p.id AND l.status = \'active\'
            WHERE p.status = \'active\' GROUP BY p.id ORDER BY available_qty ASC, p.name ASC LIMIT 12');
    }

    private function availableContracts()
    {
        return \Model::fetchAll("SELECT c.id, c.principal_amount, c.status, u.full_name AS customer_name
            FROM contracts c JOIN users u ON u.id = c.customer_id
            LEFT JOIN plugin_inventory_sales s ON s.contract_id = c.id
            WHERE c.status IN ('active','pending') AND s.id IS NULL
            ORDER BY c.id DESC LIMIT 200");
    }

    private function sendCsv($filename, array $rows)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"');
        header('Cache-Control: private, no-store, max-age=0');
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'wb');
        if (!$rows) {
            fputcsv($output, ['نتیجه‌ای وجود ندارد']);
            fclose($output);
            exit;
        }
        $headers = array_keys((array) $rows[0]);
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $key) {
                $value = $row[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $value = (string) $value;
                if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value)) { $value = "'" . $value; }
                $values[] = $value;
            }
            fputcsv($output, $values);
        }
        fclose($output);
        exit;
    }
}
