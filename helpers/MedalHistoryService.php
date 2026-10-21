<?php

final class MedalHistoryService
{
    public static function record($userMedalId, $action, $reason, $actorId, array $snapshot = [])
    {
        Model::execute(
            'INSERT INTO user_medal_history (user_medal_id, action, reason, performed_by, snapshot_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $userMedalId, (string) $action, trim((string) $reason) ?: null, $actorId ? (int) $actorId : null, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }
}
