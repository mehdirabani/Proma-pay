<?php

namespace Proma\Plugins\Affiliate\Controllers;

use Proma\Plugins\Affiliate\Services\AffiliateService;

final class AffiliateController extends \Controller
{
    public function wallet(): void
    {
        $this->requireRole('customer');
        $userId = (int) \Auth::id();
        $this->render('plugin:proma-affiliate/customer/wallet', [
            'title' => 'کسب درآمد', 'wallet' => AffiliateService::wallet($userId),
        ]);
    }

    public function savePayoutDetails(): void
    {
        $this->requireRole('customer');
        $this->onlyPost();
        try {
            AffiliateService::savePayoutDetails((int) \Auth::id(), $_POST);
            \set_flash('success', 'اطلاعات کارت و حساب بانکی ذخیره شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ذخیره اطلاعات بانکی انجام نشد.');
        }
        \redirect('plugin/affiliate/wallet');
    }

    public function requestWithdrawal(): void
    {
        $this->requireRole('customer');
        $this->onlyPost();
        try {
            AffiliateService::requestWithdrawal((int) \Auth::id(), $_POST);
            \set_flash('success', 'درخواست برداشت برای بررسی مدیریت ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ثبت درخواست برداشت انجام نشد.');
        }
        \redirect('plugin/affiliate/wallet');
    }

    public function convertCredit(): void
    {
        $this->requireRole('customer');
        $this->onlyPost();
        try {
            AffiliateService::convertToPurchaseCredit((int) \Auth::id(), $_POST['amount_toman'] ?? 0);
            \set_flash('success', 'اعتبار افیلیت به اعتبار خرید تبدیل شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'تبدیل اعتبار انجام نشد.');
        }
        \redirect('plugin/affiliate/wallet');
    }

    public function admin(): void
    {
        $this->requireRole('admin');
        $query = trim((string) ($_GET['q'] ?? ''));
        $this->render('plugin:proma-affiliate/admin/index', [
            'title' => 'مدیریت افیلیت مارکتینگ', 'settings' => AffiliateService::settings(),
            'stats' => AffiliateService::adminStats(), 'withdrawals' => AffiliateService::withdrawals('pending'),
            'members' => AffiliateService::adminMembers($query), 'memberQuery' => $query,
            'sales' => \Model::fetchAll('SELECT s.*,c.contract_number,a.full_name AS affiliate_name,u.full_name AS customer_name FROM proma_affiliate_sales s JOIN contracts c ON c.id=s.contract_id JOIN users a ON a.id=s.member_user_id JOIN users u ON u.id=s.customer_id ORDER BY s.id DESC LIMIT 100'),
            'referrals' => \Model::fetchAll('SELECT r.*, a.full_name AS referrer_name, b.full_name AS referred_name FROM proma_affiliate_referrals r JOIN users a ON a.id=r.referrer_user_id JOIN users b ON b.id=r.referred_user_id ORDER BY r.id DESC LIMIT 100'),
        ]);
    }

    public function searchReferrers(): void
    {
        $this->requireRole('admin');
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'], 'full_name' => (string) ($item['full_name'] ?? ''),
                'mobile' => (string) ($item['mobile'] ?? ''), 'national_id' => (string) ($item['national_id'] ?? ''),
                'role_label' => 'کد معرف: ' . (string) ($item['referral_code'] ?? ''),
            ];
        }, AffiliateService::searchReferrers($query));
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function adjustBalance($id): void
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            AffiliateService::adjustMemberBalance((int) $id, (int) \Auth::id(), $_POST);
            \set_flash('success', 'موجودی افیلیت با ثبت در گردش حساب اصلاح شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'اصلاح موجودی انجام نشد.');
        }
        \redirect('plugin/affiliate/admin' . (!empty($_GET['q']) ? '?q=' . rawurlencode((string) $_GET['q']) : ''));
    }

    public function saveSettings(): void
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            AffiliateService::saveSettings($_POST, (int) \Auth::id());
            \set_flash('success', 'سناریوی پاداش افیلیت ذخیره شد.');
        } catch (\Throwable $e) {
            \set_flash('error', 'ذخیره سناریوی افیلیت انجام نشد.');
        }
        \redirect('plugin/affiliate/admin');
    }

    public function withdrawals(): void
    {
        $this->requireRole('admin');
        $status = (string) ($_GET['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'approved', 'paid', 'rejected'], true)) {
            $status = 'pending';
        }
        $this->render('plugin:proma-affiliate/admin/withdrawals', [
            'title' => 'درخواست‌های برداشت افیلیت', 'status' => $status,
            'withdrawals' => AffiliateService::withdrawals($status),
        ]);
    }

    public function reviewWithdrawal($id): void
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            AffiliateService::reviewWithdrawal((int) $id, (int) \Auth::id(), (string) ($_POST['decision'] ?? ''), (string) ($_POST['review_note'] ?? ''));
            \set_flash('success', 'وضعیت درخواست برداشت ثبت شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'رسیدگی به برداشت انجام نشد.');
        }
        \redirect('plugin/affiliate/withdrawals' . (!empty($_GET['status']) ? '?status=' . rawurlencode((string) $_GET['status']) : ''));
    }
}
