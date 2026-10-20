<?php

namespace Proma\Plugins\Affiliate\Services;

use Proma\Plugins\Affiliate\Domain\RewardTrigger;

final class AffiliateService
{
    public static function settings(): array
    {
        $settings = [];
        foreach (\Model::fetchAll('SELECT setting_key, setting_value FROM proma_affiliate_settings') as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        return $settings + [
            'program_enabled' => '1', 'reward_per_customer' => '100000',
            'reward_trigger' => 'first_purchase', 'milestone_enabled' => '1', 'milestone_count' => '10', 'milestone_multiplier' => '2',
            'minimum_withdrawal_toman' => '100000',
        ];
    }

    public static function saveSettings(array $input, int $actorId): void
    {
        $amount = max(0, (int) \normalize_money($input['reward_per_customer'] ?? 0));
        $count = max(2, min(100000, (int) \to_english_digits($input['milestone_count'] ?? 10)));
        $multiplier = max(1, min(10, (int) \to_english_digits($input['milestone_multiplier'] ?? 2)));
        $minimumWithdrawal = max(0, (int) \normalize_money($input['minimum_withdrawal_toman'] ?? 100000));
        $values = [
            'program_enabled' => !empty($input['program_enabled']) ? '1' : '0',
            'reward_per_customer' => (string) $amount,
            'reward_trigger' => in_array($input['reward_trigger'] ?? '', ['first_purchase', 'sale_created', 'customer_created'], true) ? (string) $input['reward_trigger'] : 'first_purchase',
            'milestone_enabled' => !empty($input['milestone_enabled']) ? '1' : '0',
            'milestone_count' => (string) $count,
            'milestone_multiplier' => (string) $multiplier,
            'minimum_withdrawal_toman' => (string) $minimumWithdrawal,
        ];
        foreach ($values as $key => $value) {
            \Model::execute(
                'INSERT INTO proma_affiliate_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()',
                [$key, $value, $actorId]
            );
        }
    }

    public static function ensureMember(int $userId): array
    {
        $user = \Model::fetch("SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active' LIMIT 1", [$userId]);
        if (!$user) throw new \InvalidArgumentException('حساب مشتری فعال پیدا نشد.');
        $member = \Model::fetch('SELECT * FROM proma_affiliate_members WHERE user_id = ? LIMIT 1', [$userId]);
        if ($member) return $member;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $code = 'PR' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
            try {
                \Model::execute('INSERT INTO proma_affiliate_members (user_id, referral_code, created_at) VALUES (?, ?, NOW())', [$userId, $code]);
                $member = \Model::fetch('SELECT * FROM proma_affiliate_members WHERE user_id = ? LIMIT 1', [$userId]);
                if ($member) return $member;
            } catch (\Throwable $e) {
                if ($attempt === 3) throw $e;
            }
        }
        throw new \RuntimeException('کد معرفی ساخته نشد.');
    }

    public static function registerReferral(int $referredUserId, string $code): void
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($code)));
        if ($referredUserId <= 0 || $code === '') return;
        $settings = self::settings();
        if (($settings['program_enabled'] ?? '0') !== '1') return;
        $referrer = \Model::fetch('SELECT user_id FROM proma_affiliate_members WHERE referral_code = ? AND status = \'active\' LIMIT 1', [$code]);
        if (!$referrer || (int) $referrer['user_id'] === $referredUserId) return;
        self::ensureMember($referredUserId);
        $referrerId = (int) $referrer['user_id'];
        $referred = \Model::fetch("SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active' LIMIT 1", [$referredUserId]);
        if (!$referred) return;

        \Model::begin();
        try {
            $lockedCustomer = \Model::fetch("SELECT id FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1 FOR UPDATE", [$referredUserId]);
            if (!$lockedCustomer) { \Model::commit(); return; }
            $inserted = \Model::execute(
                "INSERT IGNORE INTO proma_affiliate_referrals (referrer_user_id, referred_user_id, referral_code, status, created_at) VALUES (?, ?, ?, 'pending', NOW())",
                [$referrerId, $referredUserId, $code]
            );
            if ((int) $inserted !== 1) {
                \Model::commit();
                return;
            }
            $referralId = (int) \Model::lastInsertId();
            if (RewardTrigger::matches($settings['reward_trigger'] ?? 'first_purchase', 'customer_created')) {
                self::awardReferralLocked($referralId, $settings);
            }
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public static function registerCustomerReferral(int $referredUserId, int $referrerUserId): void
    {
        if ($referredUserId <= 0 || $referrerUserId <= 0 || $referredUserId === $referrerUserId) return;
        $member = \Model::fetch("SELECT referral_code FROM proma_affiliate_members WHERE user_id=? AND status='active' LIMIT 1", [$referrerUserId]);
        if (!$member) return;
        self::registerReferral($referredUserId, (string) $member['referral_code']);
    }

    /** Record a separate affiliate commission attribution for one contract sale. */
    public static function registerContractReferral(int $contractId, int $customerId, int $referrerUserId): void
    {
        if ($contractId <= 0 || $customerId <= 0 || $referrerUserId <= 0 || $customerId === $referrerUserId) return;
        $settings = self::settings();
        if (($settings['program_enabled'] ?? '0') !== '1') return;
        $contract = \Model::fetch("SELECT id FROM contracts WHERE id=? AND customer_id=? AND status<>'cancelled' LIMIT 1", [$contractId, $customerId]);
        $member = \Model::fetch("SELECT m.referral_code FROM proma_affiliate_members m JOIN users u ON u.id=m.user_id WHERE m.user_id=? AND m.status='active' AND u.role='customer' AND u.status='active' LIMIT 1", [$referrerUserId]);
        $customer = \Model::fetch("SELECT id FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1", [$customerId]);
        if (!$contract || !$member || !$customer) return;

        \Model::begin();
        try {
            $lockedCustomer = \Model::fetch("SELECT id FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1 FOR UPDATE", [$customerId]);
            if (!$lockedCustomer) { \Model::commit(); return; }
            $existing = \Model::fetch('SELECT id FROM proma_affiliate_sales WHERE contract_id=? LIMIT 1 FOR UPDATE', [$contractId]);
            if ($existing) { \Model::commit(); return; }
            $inserted = \Model::execute(
                "INSERT IGNORE INTO proma_affiliate_sales (contract_id, customer_id, member_user_id, referral_code, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$contractId, $customerId, $referrerUserId, (string) $member['referral_code']]
            );
            if ((int) $inserted !== 1) { \Model::commit(); return; }
            // A contract-specific affiliate selection takes precedence over an
            // earlier signup attribution for this same sale.
            \Model::execute("UPDATE proma_affiliate_referrals SET status='rejected' WHERE referred_user_id=? AND status='pending'", [$customerId]);
            if (RewardTrigger::matches($settings['reward_trigger'] ?? 'first_purchase', 'sale_created')) {
                self::awardContractSaleLocked($contractId, $settings, null);
            }
            \Model::commit();
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }

    /** Qualify the selected contract referrer on the first successful payment for that contract. */
    public static function completeContractReferralPurchase(int $contractId, int $paymentId): bool
    {
        if ($contractId <= 0) return false;
        $snapshot = \Model::fetch('SELECT customer_id FROM proma_affiliate_sales WHERE contract_id=? LIMIT 1', [$contractId]);
        if (!$snapshot) return false;
        \Model::begin();
        try {
            $customerId = (int) ($snapshot['customer_id'] ?? 0);
            if ($customerId <= 0 || !\Model::fetch('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE', [$customerId])) { \Model::commit(); return false; }
            $sale = \Model::fetch('SELECT id, customer_id, status FROM proma_affiliate_sales WHERE contract_id=? LIMIT 1 FOR UPDATE', [$contractId]);
            if (!$sale) { \Model::commit(); return false; }
            $settings = self::settings();
            if (($sale['status'] ?? '') === 'pending' && ($settings['program_enabled'] ?? '0') === '1'
                && RewardTrigger::matches($settings['reward_trigger'] ?? 'first_purchase', 'first_purchase')) {
                self::awardContractSaleLocked($contractId, $settings, $paymentId > 0 ? $paymentId : null);
            }
            \Model::commit();
            return true;
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }

    /** Qualify a signup-code referral on the first active contract created for that customer. */
    public static function completeReferralSale(int $contractId, int $customerId): void
    {
        if ($contractId <= 0 || $customerId <= 0) return;
        $settings = self::settings();
        if (($settings['program_enabled'] ?? '0') !== '1'
            || !RewardTrigger::matches($settings['reward_trigger'] ?? 'first_purchase', 'sale_created')) return;

        \Model::begin();
        try {
            $member = \Model::fetch('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE', [$customerId]);
            if (!$member) { \Model::commit(); return; }
            $contract = \Model::fetch("SELECT id FROM contracts WHERE id=? AND customer_id=? AND status='active' LIMIT 1 FOR UPDATE", [$contractId, $customerId]);
            if (!$contract) { \Model::commit(); return; }
            // A contract-selected referrer takes precedence over a signup code.
            $sale = \Model::fetch('SELECT id FROM proma_affiliate_sales WHERE contract_id=? LIMIT 1 FOR UPDATE', [$contractId]);
            if ($sale) { \Model::commit(); return; }
            $referral = \Model::fetch("SELECT id FROM proma_affiliate_referrals WHERE referred_user_id=? AND status='pending' LIMIT 1 FOR UPDATE", [$customerId]);
            if ($referral) self::awardReferralLocked((int) $referral['id'], $settings);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    /** Caller holds a transaction; contract_id uniqueness and transaction key make awards retry-safe. */
    private static function awardContractSaleLocked(int $contractId, array $settings, ?int $paymentId): void
    {
        $saleSnapshot = \Model::fetch('SELECT customer_id FROM proma_affiliate_sales WHERE contract_id=? LIMIT 1', [$contractId]);
        if (!$saleSnapshot) return;
        $customerId = (int) $saleSnapshot['customer_id'];
        if ($customerId <= 0 || !\Model::fetch('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE', [$customerId])) return;
        $sale = \Model::fetch("SELECT * FROM proma_affiliate_sales WHERE contract_id=? AND status='pending' LIMIT 1 FOR UPDATE", [$contractId]);
        if (!$sale) return;
        $priorSaleReward = \Model::fetch("SELECT id FROM proma_affiliate_sales WHERE customer_id=? AND contract_id<>? AND status='qualified' AND reward_amount>0 LIMIT 1 FOR UPDATE", [$customerId, $contractId]);
        $priorReferralReward = \Model::fetch("SELECT id FROM proma_affiliate_referrals WHERE referred_user_id=? AND status='qualified' AND reward_amount>0 LIMIT 1 FOR UPDATE", [$customerId]);
        if ($priorSaleReward || $priorReferralReward) {
            \Model::execute("UPDATE proma_affiliate_sales SET status='qualified', reward_amount=0, payment_id=?, qualified_at=NOW() WHERE contract_id=? AND status='pending'", [$paymentId, $contractId]);
            return;
        }
        $memberId = (int) $sale['member_user_id'];
        $saleCount = (int) (\Model::fetch("SELECT COUNT(*) AS total FROM proma_affiliate_sales WHERE member_user_id=? AND status='qualified' AND reward_amount>0", [$memberId])['total'] ?? 0) + 1;
        $base = max(0, (int) \normalize_money($settings['reward_per_customer'] ?? 0));
        $multiplier = 1;
        $milestone = max(2, (int) ($settings['milestone_count'] ?? 10));
        if (($settings['milestone_enabled'] ?? '0') === '1' && $saleCount % $milestone === 0) {
            $multiplier = max(1, min(10, (int) ($settings['milestone_multiplier'] ?? 2)));
        }
        if ($base > intdiv(PHP_INT_MAX, $multiplier)) throw new \OverflowException('Affiliate reward exceeds the PHP integer range.');
        $reward = $base * $multiplier;
        \Model::execute("UPDATE proma_affiliate_sales SET status='qualified', reward_amount=?, payment_id=?, qualified_at=NOW() WHERE contract_id=? AND status='pending'", [$reward, $paymentId, $contractId]);
        if ($reward <= 0) return;
        \Model::execute('UPDATE proma_affiliate_members SET reward_balance=reward_balance+?,lifetime_rewards=lifetime_rewards+?,updated_at=NOW() WHERE user_id=?', [$reward, $reward, $memberId]);
        $member = \Model::fetch('SELECT reward_balance,purchase_credit FROM proma_affiliate_members WHERE user_id=? FOR UPDATE', [$memberId]);
        $key = 'affiliate-sale:' . $contractId;
        $description = $multiplier > 1 ? 'کمیسیون قرارداد فروشی · پاداش نقطه عطف' : 'کمیسیون فروش قرارداد';
        \Model::execute(
            "INSERT INTO proma_affiliate_transactions (member_user_id,transaction_type,amount_toman,reward_balance_after,purchase_credit_after,description,idempotency_key,created_at) VALUES (?,'referral_reward',?,?,?,?,?,NOW())",
            [$memberId, $reward, $member['reward_balance'], $member['purchase_credit'], $description, $key]
        );
        self::postAccounting($memberId, 'increase', $reward, $description, 'sale', $contractId, $key);
    }

    /** Called by the payment.completed hook; only the first paid transaction qualifies a pending referral. */
    public static function completeReferralPurchase(int $customerId, int $paymentId): void
    {
        if ($customerId <= 0 || $paymentId <= 0) return;
        $settings = self::settings();
        if (($settings['program_enabled'] ?? '0') !== '1' || !RewardTrigger::matches($settings['reward_trigger'] ?? 'first_purchase', 'first_purchase')) return;
        \Model::begin();
        try {
            if (!\Model::fetch('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE', [$customerId])) { \Model::commit(); return; }
            $referral = \Model::fetch("SELECT id FROM proma_affiliate_referrals WHERE referred_user_id = ? AND status = 'pending' LIMIT 1 FOR UPDATE", [$customerId]);
            if ($referral) self::awardReferralLocked((int) $referral['id'], $settings);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    /** Caller holds a transaction; locking and a unique wallet key make qualification retry-safe. */
    private static function awardReferralLocked(int $referralId, array $settings): void
    {
        $referralSnapshot = \Model::fetch('SELECT referred_user_id FROM proma_affiliate_referrals WHERE id=? LIMIT 1', [$referralId]);
        if (!$referralSnapshot) return;
        $referredId = (int) $referralSnapshot['referred_user_id'];
        if ($referredId <= 0 || !\Model::fetch('SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE', [$referredId])) return;
        $referral = \Model::fetch("SELECT * FROM proma_affiliate_referrals WHERE id = ? LIMIT 1 FOR UPDATE", [$referralId]);
        if (!$referral || ($referral['status'] ?? '') !== 'pending') return;
        $referrerId = (int) $referral['referrer_user_id'];
        $priorSaleReward = \Model::fetch("SELECT id FROM proma_affiliate_sales WHERE customer_id=? AND status='qualified' AND reward_amount>0 LIMIT 1 FOR UPDATE", [$referredId]);
        if ($priorSaleReward) {
            \Model::execute("UPDATE proma_affiliate_referrals SET status='qualified', reward_amount=0 WHERE id=? AND status='pending'", [$referralId]);
            return;
        }
        $referralCount = (int) (\Model::fetch("SELECT COUNT(*) AS total FROM proma_affiliate_referrals WHERE referrer_user_id = ? AND status = 'qualified' AND reward_amount>0", [$referrerId])['total'] ?? 0) + 1;
        $base = max(0, (int) \normalize_money($settings['reward_per_customer'] ?? 0));
        $multiplier = 1;
        $milestone = max(2, (int) ($settings['milestone_count'] ?? 10));
        if (($settings['milestone_enabled'] ?? '0') === '1' && $referralCount % $milestone === 0) {
            $multiplier = max(1, min(10, (int) ($settings['milestone_multiplier'] ?? 2)));
        }
        if ($base > intdiv(PHP_INT_MAX, $multiplier)) throw new \OverflowException('Affiliate reward exceeds the PHP integer range.');
        $reward = $base * $multiplier;
        \Model::execute("UPDATE proma_affiliate_referrals SET status = 'qualified', reward_amount = ? WHERE id = ? AND status = 'pending'", [$reward, $referralId]);
        if ($reward <= 0) return;
        \Model::execute('UPDATE proma_affiliate_members SET reward_balance = reward_balance + ?, lifetime_rewards = lifetime_rewards + ?, updated_at = NOW() WHERE user_id = ?', [$reward, $reward, $referrerId]);
        $member = \Model::fetch('SELECT reward_balance, purchase_credit FROM proma_affiliate_members WHERE user_id = ? FOR UPDATE', [$referrerId]);
        $key = 'affiliate-referral:' . $referredId;
        \Model::execute(
            "INSERT INTO proma_affiliate_transactions (member_user_id, referral_id, transaction_type, amount_toman, reward_balance_after, purchase_credit_after, description, idempotency_key, created_at) VALUES (?, ?, 'referral_reward', ?, ?, ?, ?, ?, NOW())",
            [$referrerId, $referralId, $reward, $member['reward_balance'], $member['purchase_credit'], $multiplier > 1 ? 'کمیسیون خرید معرفی‌شده · پاداش نقطه عطف' : 'کمیسیون خرید مشتری معرفی‌شده', $key]
        );
        self::postAccounting($referrerId, 'increase', $reward, 'کمیسیون افیلیت بابت خرید مشتری معرفی‌شده', 'affiliate_referral', $referralId, $key);
    }

    public static function wallet(int $userId): array
    {
        $member = self::ensureMember($userId);
        $referrals = \Model::fetchAll(
            'SELECT r.*, u.full_name AS referred_name FROM proma_affiliate_referrals r JOIN users u ON u.id = r.referred_user_id WHERE r.referrer_user_id = ? ORDER BY r.id DESC LIMIT 100',
            [$userId]
        );
        $transactions = \Model::fetchAll('SELECT * FROM proma_affiliate_transactions WHERE member_user_id = ? ORDER BY id DESC LIMIT 50', [$userId]);
        $withdrawals = \Model::fetchAll('SELECT * FROM proma_affiliate_withdrawals WHERE member_user_id = ? ORDER BY id DESC LIMIT 20', [$userId]);
        $sales = \Model::fetchAll('SELECT s.*,c.contract_number,u.full_name AS customer_name FROM proma_affiliate_sales s JOIN contracts c ON c.id=s.contract_id JOIN users u ON u.id=s.customer_id WHERE s.member_user_id=? ORDER BY s.id DESC LIMIT 50', [$userId]);
        $pending = \Model::fetch("SELECT COALESCE(SUM(amount_toman),0) AS total FROM proma_affiliate_withdrawals WHERE member_user_id = ? AND status IN ('pending','approved')", [$userId]);
        $settings = self::settings();
        return [
            'member' => $member, 'referrals' => $referrals, 'sales' => $sales, 'transactions' => $transactions, 'withdrawals' => $withdrawals,
            'pending_withdrawals' => (int) ($pending['total'] ?? 0), 'settings' => $settings,
            'referral_url' => rtrim(\detected_base_url(), '/') . '/index.php?route=auth/register&ref=' . rawurlencode((string) $member['referral_code']),
        ];
    }

    public static function requestWithdrawal(int $userId, array $input): void
    {
        $amount = max(0, (int) \normalize_money($input['amount_toman'] ?? 0));
        $settings = self::settings();
        $minimum = max(0, (int) \normalize_money($settings['minimum_withdrawal_toman'] ?? 100000));
        if (($settings['program_enabled'] ?? '0') !== '1') throw new \InvalidArgumentException('ثبت درخواست برداشت در حال حاضر غیرفعال است.');
        if ($amount <= 0 || $amount < $minimum) throw new \InvalidArgumentException('مبلغ درخواست کمتر از حداقل برداشت تعیین‌شده است.');
        \Model::begin();
        try {
            $member = \Model::fetch('SELECT * FROM proma_affiliate_members WHERE user_id = ? FOR UPDATE', [$userId]);
            if (!$member || ($member['status'] ?? '') !== 'active') throw new \InvalidArgumentException('حساب افیلیت فعال نیست.');
            $payee = trim((string) ($member['payout_payee_name'] ?? ''));
            $iban = (string) ($member['payout_iban'] ?? '');
            $card = (string) ($member['payout_card_number'] ?? '');
            $account = (string) ($member['payout_account_number'] ?? '');
            if ($payee === '' || ($iban === '' && $card === '' && $account === '')) throw new \InvalidArgumentException('ابتدا اطلاعات کارت یا حساب بانکی را ثبت کنید.');
            $pending = (int) (\Model::fetch("SELECT COALESCE(SUM(amount_toman),0) AS total FROM proma_affiliate_withdrawals WHERE member_user_id = ? AND status IN ('pending','approved')", [$userId])['total'] ?? 0);
            if ($amount > max(0, (int) \normalize_money($member['reward_balance']) - $pending)) throw new \InvalidArgumentException('مبلغ درخواست از اعتبار قابل برداشت بیشتر است.');
            \Model::execute('INSERT INTO proma_affiliate_withdrawals (member_user_id, amount_toman, payee_name, iban, card_number, account_number, status, request_note, created_at) VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, NOW())', [$userId, $amount, $payee, $iban ?: null, $card ?: null, $account ?: null, mb_substr(trim(strip_tags((string) ($input['request_note'] ?? ''))), 0, 500, 'UTF-8') ?: null]);
            \Model::commit();
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }

    public static function savePayoutDetails(int $userId, array $input): void
    {
        $member = self::ensureMember($userId);
        $payee = mb_substr(trim(strip_tags((string) ($input['payee_name'] ?? ''))), 0, 190, 'UTF-8');
        $card = preg_replace('/\D+/', '', \to_english_digits((string) ($input['card_number'] ?? '')));
        $account = preg_replace('/\D+/', '', \to_english_digits((string) ($input['account_number'] ?? '')));
        $iban = strtoupper(preg_replace('/[^A-Z0-9]/', '', \to_english_digits(trim((string) ($input['iban'] ?? '')))));
        if ($payee === '') throw new \InvalidArgumentException('نام صاحب حساب را وارد کنید.');
        if ($card !== '' && !preg_match('/^\d{16}$/', $card)) throw new \InvalidArgumentException('شماره کارت باید ۱۶ رقم باشد.');
        if ($account !== '' && !preg_match('/^\d{6,30}$/', $account)) throw new \InvalidArgumentException('شماره حساب باید بین ۶ تا ۳۰ رقم باشد.');
        if ($iban !== '' && !preg_match('/^IR\d{24}$/', $iban)) throw new \InvalidArgumentException('شماره شبا باید با IR و ۲۴ رقم وارد شود.');
        if ($card === '' && $account === '' && $iban === '') throw new \InvalidArgumentException('شماره کارت، حساب یا شبا را وارد کنید.');
        \Model::execute('UPDATE proma_affiliate_members SET payout_payee_name=?, payout_card_number=?, payout_account_number=?, payout_iban=?, updated_at=NOW() WHERE user_id=?', [$payee, $card ?: null, $account ?: null, $iban ?: null, $userId]);
    }

    public static function convertToPurchaseCredit(int $userId, $amountInput): void
    {
        $amount = max(0, (int) \normalize_money($amountInput));
        if ($amount <= 0) throw new \InvalidArgumentException('مبلغ تبدیل معتبر نیست.');
        \Model::begin();
        try {
            $member = \Model::fetch('SELECT * FROM proma_affiliate_members WHERE user_id = ? FOR UPDATE', [$userId]);
            if (!$member || $amount > (int) \normalize_money($member['reward_balance'])) throw new \InvalidArgumentException('مبلغ از اعتبار افیلیت شما بیشتر است.');
            \Model::execute('UPDATE proma_affiliate_members SET reward_balance = reward_balance - ?, purchase_credit = purchase_credit + ?, updated_at = NOW() WHERE user_id = ?', [$amount, $amount, $userId]);
            $after = \Model::fetch('SELECT reward_balance, purchase_credit FROM proma_affiliate_members WHERE user_id = ?', [$userId]);
            \Model::execute("INSERT INTO proma_affiliate_transactions (member_user_id, transaction_type, amount_toman, reward_balance_after, purchase_credit_after, description, idempotency_key, created_at) VALUES (?, 'purchase_conversion', ?, ?, ?, ?, ?, NOW())", [$userId, -$amount, $after['reward_balance'], $after['purchase_credit'], 'تبدیل موجودی افیلیت به اعتبار خرید', 'affiliate-convert:' . bin2hex(random_bytes(12))]);
            \Model::commit();
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }

    public static function withdrawals(string $status = 'pending'): array
    {
        $status = in_array($status, ['pending', 'approved', 'paid', 'rejected'], true) ? $status : 'pending';
        return \Model::fetchAll('SELECT w.*, u.full_name, u.mobile FROM proma_affiliate_withdrawals w JOIN users u ON u.id = w.member_user_id WHERE w.status = ? ORDER BY w.id DESC LIMIT 200', [$status]);
    }

    public static function reviewWithdrawal(int $withdrawalId, int $actorId, string $decision, string $note = ''): void
    {
        if (!in_array($decision, ['approved', 'paid', 'rejected'], true)) throw new \InvalidArgumentException('وضعیت برداشت معتبر نیست.');
        \Model::begin();
        try {
            $withdrawal = \Model::fetch('SELECT * FROM proma_affiliate_withdrawals WHERE id = ? FOR UPDATE', [$withdrawalId]);
            $status = (string) ($withdrawal['status'] ?? '');
            if (!$withdrawal || !in_array($status, ['pending', 'approved'], true)) throw new \InvalidArgumentException('درخواست برداشت در انتظار رسیدگی نیست.');
            $note = mb_substr(trim(strip_tags($note)), 0, 500, 'UTF-8');
            if ($decision === 'approved' && $status !== 'pending') throw new \InvalidArgumentException('فقط درخواست در انتظار را می‌توان تأیید کرد.');
            if ($decision === 'paid' && $status !== 'approved') throw new \InvalidArgumentException('ابتدا درخواست برداشت را تأیید کنید.');
            if ($decision === 'rejected') {
                \Model::execute('UPDATE proma_affiliate_withdrawals SET status=\'rejected\', review_note=?, reviewed_by=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?', [$note ?: null, $actorId, $withdrawalId]);
            } elseif ($decision === 'approved') {
                \Model::execute('UPDATE proma_affiliate_withdrawals SET status=\'approved\', review_note=?, reviewed_by=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?', [$note ?: null, $actorId, $withdrawalId]);
            } else {
                $member = \Model::fetch('SELECT * FROM proma_affiliate_members WHERE user_id = ? FOR UPDATE', [(int) $withdrawal['member_user_id']]);
                $amount = (int) \normalize_money($withdrawal['amount_toman']);
                $otherReserved = (int) (\Model::fetch("SELECT COALESCE(SUM(amount_toman),0) AS total FROM proma_affiliate_withdrawals WHERE member_user_id=? AND id<>? AND status IN ('pending','approved')", [(int) $withdrawal['member_user_id'], $withdrawalId])['total'] ?? 0);
                if (!$member || $amount > max(0, (int) \normalize_money($member['reward_balance']) - $otherReserved)) throw new \InvalidArgumentException('موجودی قابل برداشت برای تسویه کافی نیست.');
                \Model::execute('UPDATE proma_affiliate_members SET reward_balance = reward_balance - ?, updated_at = NOW() WHERE user_id = ?', [$amount, (int) $withdrawal['member_user_id']]);
                $after = \Model::fetch('SELECT reward_balance, purchase_credit FROM proma_affiliate_members WHERE user_id = ?', [(int) $withdrawal['member_user_id']]);
                \Model::execute("INSERT INTO proma_affiliate_transactions (member_user_id, transaction_type, amount_toman, reward_balance_after, purchase_credit_after, description, idempotency_key, created_by, created_at) VALUES (?, 'withdrawal_paid', ?, ?, ?, ?, ?, ?, NOW())", [(int) $withdrawal['member_user_id'], -$amount, $after['reward_balance'], $after['purchase_credit'], 'پرداخت برداشت افیلیت #' . $withdrawalId, 'affiliate-withdrawal:' . $withdrawalId, $actorId]);
                self::postAccounting((int) $withdrawal['member_user_id'], 'decrease', $amount, 'تسویه برداشت افیلیت #' . $withdrawalId, 'affiliate_withdrawal', $withdrawalId, 'affiliate-withdrawal:' . $withdrawalId);
                \Model::execute('UPDATE proma_affiliate_withdrawals SET status=\'paid\', review_note=?, reviewed_by=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?', [$note ?: null, $actorId, $withdrawalId]);
            }
            \Model::commit();
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }

    public static function adminMembers(string $query = '', int $limit = 50): array
    {
        $query = trim(\to_english_digits($query));
        $params = [];
        $where = "WHERE u.role='customer'";
        if ($query !== '') {
            $like = '%' . $query . '%';
            $where .= ' AND (m.referral_code LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.national_id LIKE ?)';
            $params = [$like, $like, $like, $like];
        }
        $limit = max(1, min(100, $limit));
        return \Model::fetchAll("SELECT m.*,u.full_name,u.mobile,u.national_id,
                COALESCE((SELECT SUM(w.amount_toman) FROM proma_affiliate_withdrawals w WHERE w.member_user_id=m.user_id AND w.status IN ('pending','approved')),0) AS reserved_withdrawals
            FROM proma_affiliate_members m JOIN users u ON u.id=m.user_id {$where}
            ORDER BY m.reward_balance DESC,m.user_id DESC LIMIT {$limit}", $params);
    }

    public static function searchReferrers(string $query, int $limit = 12): array
    {
        $query = trim(\to_english_digits($query));
        if (mb_strlen($query, 'UTF-8') < 2) return [];
        $like = '%' . $query . '%';
        $limit = max(1, min(20, $limit));
        return \Model::fetchAll("SELECT m.user_id AS id,u.full_name,u.mobile,u.national_id,m.referral_code
            FROM proma_affiliate_members m JOIN users u ON u.id=m.user_id
            WHERE m.status='active' AND u.role='customer' AND u.status='active'
              AND (m.referral_code LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.national_id LIKE ?)
            ORDER BY u.full_name LIMIT {$limit}", [$like, $like, $like, $like]);
    }

    public static function adjustMemberBalance(int $userId, int $actorId, array $input): void
    {
        $amount = max(0, (int) \normalize_money($input['amount_toman'] ?? 0));
        $direction = (string) ($input['direction'] ?? '');
        $note = mb_substr(trim(strip_tags((string) ($input['note'] ?? ''))), 0, 500, 'UTF-8');
        if ($amount <= 0 || !in_array($direction, ['increase', 'decrease'], true) || $note === '') throw new \InvalidArgumentException('مبلغ، نوع اصلاح و دلیل آن را وارد کنید.');
        \Model::begin();
        try {
            $member = \Model::fetch('SELECT * FROM proma_affiliate_members WHERE user_id=? FOR UPDATE', [$userId]);
            $user = \Model::fetch("SELECT id FROM users WHERE id=? AND role='customer' LIMIT 1", [$userId]);
            if (!$member || !$user) throw new \InvalidArgumentException('بازاریاب افیلیت پیدا نشد.');
            $before = (int) \normalize_money($member['reward_balance']);
            $delta = $direction === 'increase' ? $amount : -$amount;
            $afterBalance = $before + $delta;
            $reserved = (int) (\Model::fetch("SELECT COALESCE(SUM(amount_toman),0) AS total FROM proma_affiliate_withdrawals WHERE member_user_id=? AND status IN ('pending','approved')", [$userId])['total'] ?? 0);
            if ($afterBalance < $reserved) throw new \InvalidArgumentException('موجودی جدید نمی‌تواند از درخواست‌های برداشت در جریان کمتر باشد.');
            \Model::execute('UPDATE proma_affiliate_members SET reward_balance=?,updated_at=NOW() WHERE user_id=?', [$afterBalance, $userId]);
            $after = \Model::fetch('SELECT reward_balance,purchase_credit FROM proma_affiliate_members WHERE user_id=?', [$userId]);
            $key = 'affiliate-adjustment:' . bin2hex(random_bytes(16));
            $description = mb_substr('اصلاح موجودی توسط مدیریت: ' . $note, 0, 255, 'UTF-8');
            \Model::execute("INSERT INTO proma_affiliate_transactions (member_user_id,transaction_type,amount_toman,reward_balance_after,purchase_credit_after,description,idempotency_key,created_by,created_at) VALUES (?,'admin_adjustment',?,?,?,?,?,?,NOW())", [$userId, $delta, $after['reward_balance'], $after['purchase_credit'], $description, $key, $actorId]);
            self::postAccounting($userId, $delta > 0 ? 'increase' : 'decrease', $amount, $description, 'admin_adjustment', $userId, $key);
            \Model::commit();
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }

    public static function adminStats(): array
    {
        $referrals = \Model::fetch("SELECT COUNT(*) AS total,COALESCE(SUM(reward_amount),0) AS rewards FROM proma_affiliate_referrals WHERE status='qualified'") ?: [];
        $sales = \Model::fetch("SELECT COUNT(*) AS total,COALESCE(SUM(reward_amount),0) AS rewards FROM proma_affiliate_sales WHERE status='qualified'") ?: [];
        $affiliates = \Model::fetch("SELECT COUNT(DISTINCT member_id) AS total FROM (SELECT referrer_user_id AS member_id FROM proma_affiliate_referrals WHERE status='qualified' UNION SELECT member_user_id AS member_id FROM proma_affiliate_sales WHERE status='qualified') AS affiliate_members") ?: [];
        $referralRewards = (int) \normalize_money($referrals['rewards'] ?? 0);
        $saleRewards = (int) \normalize_money($sales['rewards'] ?? 0);
        if ($saleRewards > PHP_INT_MAX - $referralRewards) throw new \OverflowException('Affiliate reward statistics exceed the PHP integer range.');
        return [
            'affiliates' => (int) ($affiliates['total'] ?? 0),
            'referrals' => (int) ($referrals['total'] ?? 0) + (int) ($sales['total'] ?? 0),
            'rewards' => $referralRewards + $saleRewards,
        ];
    }

    /** Compare wallet-changing affiliate transactions with their accounting projection. */
    public static function accountingReconciliation(): array
    {
        $mismatches = \Model::fetch(
            "SELECT COUNT(*) AS total
             FROM proma_affiliate_transactions tx
             LEFT JOIN accounting_ledger_entries le ON le.idempotency_key=tx.idempotency_key
             WHERE tx.transaction_type<>'purchase_conversion'
               AND (le.id IS NULL OR le.amount<>ABS(tx.amount_toman)
                    OR le.direction<>CASE WHEN tx.amount_toman<0 THEN 'decrease' ELSE 'increase' END)"
        ) ?: [];
        $orphanEntries = \Model::fetch(
            "SELECT COUNT(*) AS total
             FROM accounting_ledger_entries le
             LEFT JOIN proma_affiliate_transactions tx ON tx.idempotency_key=le.idempotency_key
             WHERE LEFT(le.entry_type,10)='affiliate_' AND tx.id IS NULL"
        ) ?: [];
        return [
            'missing_or_inconsistent' => (int) ($mismatches['total'] ?? 0),
            'orphan_ledger_entries' => (int) ($orphanEntries['total'] ?? 0),
        ];
    }

    private static function postAccounting(int $userId, string $direction, int $amount, string $description, string $referenceType, int $referenceId, string $key): void
    {
        if ($amount <= 0) throw new \InvalidArgumentException('مبلغ ثبت دفترکل افیلیت باید مثبت باشد.');
        if (!class_exists('PluginManager') || !\PluginManager::isActive('proma-accounting') || !class_exists('Proma\\Plugins\\Accounting\\Services\\LedgerService')) {
            throw new \RuntimeException('ثبت مالی افیلیت بدون دفترکل فعال Accounting مجاز نیست.');
        }
        try {
            \Proma\Plugins\Accounting\Services\LedgerService::post($userId, 'affiliate_' . $referenceType, $direction, $amount, $description, (int) (\Auth::id() ?? 0), $referenceType, $referenceId, ['affiliate' => true], $key);
        } catch (\Throwable $e) {
            if (class_exists('PluginRegistry')) \PluginRegistry::logRuntimeError('affiliate.accounting', $e);
            throw $e;
        }
    }
}
