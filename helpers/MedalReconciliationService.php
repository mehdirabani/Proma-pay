<?php

final class MedalReconciliationService
{
    public static function reconcileCustomer($userId, $actorId = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return ['awarded' => 0, 'revoked' => 0, 'restored' => 0];
        }
        $lockName = 'proma_medal_reconcile_' . $userId;
        $lock = Model::fetch('SELECT GET_LOCK(?, 5) AS acquired', [$lockName]);
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('ارزیابی مدال این مشتری هم‌اکنون در حال اجرا است.');
        }
        $result = ['awarded' => 0, 'revoked' => 0, 'restored' => 0];
        try {
            $metrics = MedalEvaluationService::metricsFor($userId);
            $definitions = Model::fetchAll("SELECT * FROM medal_definitions WHERE is_active = 1 AND archived_at IS NULL AND award_type = 'automatic' ORDER BY id");
            foreach ($definitions as $definition) {
                $active = Model::fetch('SELECT * FROM user_medals WHERE user_id = ? AND medal_definition_id = ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 1', [$userId, (int) $definition['id']]);
                $matches = MedalEvaluationService::matches($definition, $metrics);
                $reversible = ($definition['behavior_type'] ?? 'permanent') === 'reversible';
                if ($matches && !$active) {
                    $revoked = $reversible ? Model::fetch('SELECT * FROM user_medals WHERE user_id = ? AND medal_definition_id = ? AND revoked_at IS NOT NULL ORDER BY id DESC LIMIT 1', [$userId, (int) $definition['id']]) : null;
                    if ($revoked && ($definition['reactivation_behavior'] ?? 'restore') === 'restore') {
                        Medal::restore((int) $revoked['id'], $actorId, 'شرایط مدال دوباره برقرار شد.');
                        $result['restored']++;
                    } elseif (Medal::award($userId, $definition['slug'], 'automatic', $actorId, 'اعطای خودکار پس از ارزیابی معیارها')) {
                        $result['awarded']++;
                    }
                } elseif (!$matches && $active && $reversible && ($definition['revocation_behavior'] ?? 'automatic') === 'automatic' && ($active['source'] ?? '') === 'automatic') {
                    Medal::revoke((int) $active['id'], $actorId, 'شرایط مدال وضعیت‌محور دیگر برقرار نیست.');
                    $result['revoked']++;
                }
                Model::execute('UPDATE medal_definitions SET last_evaluated_at = NOW() WHERE id = ?', [(int) $definition['id']]);
            }
            return $result;
        } finally {
            try {
                Model::fetch('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } catch (Throwable $ignored) {
            }
        }
    }
}
