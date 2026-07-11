<?php

namespace Proma\Plugins\Accounting\Controllers;

use Proma\Plugins\Accounting\Services\AccountingRepository;
use Proma\Plugins\Accounting\Services\LedgerService;

class AccountingController extends \Controller
{
    public function dashboard()
    {
        $this->render('plugin:proma-accounting/dashboard', [
            'title' => 'داشبورد حسابداری',
            'summary' => AccountingRepository::dashboard(),
            'accounts' => AccountingRepository::accounts('', 8),
        ]);
    }

    public function accounts()
    {
        $this->render('plugin:proma-accounting/accounts', [
            'title' => 'حساب کاربران',
            'accounts' => AccountingRepository::accounts($_GET['q'] ?? '', 100),
        ]);
    }

    public function ledger($userId)
    {
        $user = \User::find((int) $userId);
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'operator', 'lawyer'], true)) {
            throw new \InvalidArgumentException('کاربر حسابداری پیدا نشد.');
        }
        $this->render('plugin:proma-accounting/ledger', [
            'title' => 'دفترکل ' . ($user['full_name'] ?? ''),
            'accountUser' => $user,
            'entries' => AccountingRepository::ledger((int) $userId),
            'balance' => LedgerService::balance((int) $userId),
        ]);
    }

    public function postLedger()
    {
        $this->onlyPost();
        $entryType = trim((string) ($_POST['entry_type'] ?? ''));
        $map = [
            'bonus' => ['credit', 'پاداش'],
            'expense' => ['credit', 'هزینه قابل پرداخت'],
            'deduction' => ['debit', 'کسری/کسر'],
            'payment' => ['debit', 'پرداخت به کاربر'],
            'receipt' => ['credit', 'دریافت از کاربر'],
        ];
        if (!isset($map[$entryType])) {
            throw new \InvalidArgumentException('نوع سند حسابداری معتبر نیست.');
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $idempotency = 'manual:' . hash('sha256', $userId . '|' . $entryType . '|' . ($_POST['amount'] ?? '') . '|' . $description . '|' . microtime(true));
        LedgerService::post($userId, $entryType, $map[$entryType][0], $_POST['amount'] ?? 0, $description ?: $map[$entryType][1], \Auth::id(), 'manual', null, ['label' => $map[$entryType][1]], $idempotency);
        \set_flash('success', 'سند دفترکل با موفقیت ثبت شد.');
        \redirect('plugin/accounting/ledger/' . $userId);
    }

    public function reverseLedger($entryId)
    {
        $this->onlyPost();
        LedgerService::reverse((int) $entryId, \Auth::id(), $_POST['reason'] ?? 'اصلاح سند حسابداری');
        \set_flash('success', 'سند معکوس ثبت شد.');
        \redirect('plugin/accounting/dashboard');
    }

    public function sales()
    {
        $this->render('plugin:proma-accounting/sales', ['title' => 'فروش‌ها', 'sales' => AccountingRepository::sales()]);
    }

    public function commissions()
    {
        $this->render('plugin:proma-accounting/commissions', ['title' => 'کمیسیون فروش', 'commissions' => AccountingRepository::commissions()]);
    }
}
