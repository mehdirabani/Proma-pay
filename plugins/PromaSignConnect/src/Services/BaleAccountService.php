<?php
namespace Proma\Plugins\SignConnect\Services;

final class BaleAccountService
{
    public const CAPABILITIES = [
        'text_message','secure_message','otp_message','template_message',
        'secure_template_message','inline_buttons','multimedia','contract_created',
        'contract_signature_request','contract_signature_reminder','contract_signed',
        'installment_upcoming','installment_due','installment_overdue',
        'pre_legal_warning','legal_case_created','payment_completed',
        'contract_settled','security_otp','custom_message',
        'contract_document_available','signature_rejected','signature_expired',
        'installment_grace_ending','partial_payment_completed','suspicious_login',
        'account_locked','customer_created','profile_change_requested',
        'profile_change_approved','profile_change_rejected','file_available',
    ];

    public function all(): array
    {
        return \Model::fetchAll(
            "SELECT c.id,c.uuid,c.title,c.environment,c.external_bot_id,c.status,c.is_active,c.is_default,
                    capabilities_json,health_json,last_tested_at,last_success_at,
                    last_failure_at,archived_at,
                    (encrypted_secret IS NOT NULL AND encrypted_secret<>'') secret_stored,
                    (SELECT COUNT(*) FROM proma_connect_provider_event_mappings m WHERE m.provider_key='bale' AND m.provider_account_id=c.id AND m.is_enabled=1) enabled_event_count
             FROM proma_connect_provider_configs c
             WHERE c.provider_key='bale' AND c.archived_at IS NULL
             ORDER BY c.is_default DESC,c.id DESC LIMIT 100"
        );
    }

    public function find(int $id, bool $withSecret = false): ?array
    {
        $row = \Model::fetch(
            "SELECT * FROM proma_connect_provider_configs WHERE id=? AND provider_key='bale' LIMIT 1",
            [$id]
        );
        if ($row && $withSecret && $row['encrypted_secret']) {
            $row['api_key'] = SecretCipher::decrypt((string) $row['encrypted_secret']);
        }
        if ($row) unset($row['encrypted_secret']);
        return $row ?: null;
    }

    public function save(array $input, int $actorId): int
    {
        $id = (int) ($input['id'] ?? 0);
        $title = trim(strip_tags((string) ($input['title'] ?? '')));
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            throw new \InvalidArgumentException('عنوان داخلی بازو باید بین ۲ تا ۱۲۰ نویسه باشد.');
        }
        $botId = $this->normalizeBotId((string) ($input['bot_id'] ?? ''));
        $keyInput = trim((string) ($input['api_key'] ?? ''));
        if ($keyInput !== '' && (strlen($keyInput) < 12 || strlen($keyInput) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $keyInput))) {
            throw new \InvalidArgumentException('توکن وب‌سرویس بله معتبر نیست.');
        }
        $capabilities = [];
        foreach (BaleCapabilityCatalog::keys() as $key) {
            $capabilities[$key] = !empty($input['capabilities'][$key]);
        }
        if (!array_filter($capabilities)) throw new \InvalidArgumentException('حداقل یک نوع پیام باید فعال باشد.');
        \Model::begin();
        try {
            $before = $id ? $this->find($id) : null;
            if ($id && !$before) throw new \InvalidArgumentException('تنظیم بله پیدا نشد.');
            if (!$id && $keyInput === '') throw new \InvalidArgumentException('توکن وب‌سرویس بله وارد نشده است.');
            $duplicate = \Model::fetch(
                "SELECT id FROM proma_connect_provider_configs
                 WHERE provider_key='bale' AND title=? AND environment='production'
                   AND archived_at IS NULL AND id<>? LIMIT 1",
                [$title,$id]
            );
            if ($duplicate) throw new \InvalidArgumentException('این عنوان داخلی قبلاً استفاده شده است.');
            $encrypted = null;
            if ($keyInput !== '') {
                try { $encrypted = SecretCipher::encrypt($keyInput); }
                catch (\Throwable $e) { throw new \RuntimeException('ذخیره امن توکن با خطا روبه‌رو شد.', 0, $e); }
            }
            if ($id) {
                $connectionChanged = $keyInput !== '' || (string)$before['external_bot_id'] !== $botId;
                $sql = "UPDATE proma_connect_provider_configs
                        SET title=?,external_bot_id=?,capabilities_json=?,
                            status=?,is_active=?,is_default=?,
                            updated_by=?,updated_at=NOW()";
                $params = [$title,$botId,json_encode($capabilities),$connectionChanged?'configured_not_verified':$before['status'],$connectionChanged?0:(int)$before['is_active'],$connectionChanged?0:(int)$before['is_default'],$actorId];
                if ($encrypted !== null) { $sql .= ',encrypted_secret=?'; $params[] = $encrypted; }
                $sql .= " WHERE id=? AND provider_key='bale'";
                $params[] = $id;
                \Model::execute($sql, $params);
            } else {
                \Model::execute(
                    "INSERT INTO proma_connect_provider_configs
                     (uuid,provider_key,title,environment,external_bot_id,encrypted_secret,
                      api_base_url,status,is_active,is_default,capabilities_json,
                      retry_policy_json,created_by,created_at)
                     VALUES (?,'bale',?,'production',?,?,?,
                             'configured_not_verified',0,0,?,'{\"max_attempts\":5}',?,NOW())",
                    [$this->uuid(),$title,$botId,$encrypted,'https://safir.bale.ai/api/v3/send_message',json_encode($capabilities),$actorId]
                );
                $id = (int) \Model::lastInsertId();
            }
            $this->audit('configuration_saved',$actorId,$before,$this->find($id));
            \Model::commit();
            return $id;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function markTest(int $id, array $result, int $actorId): void
    {
        $status = !empty($result['ok']) ? 'verified' : 'test_failed';
        \Model::execute(
            "UPDATE proma_connect_provider_configs SET status=IF(is_active=1 AND ?='verified','active',?),health_json=?,
             last_tested_at=NOW(),last_success_at=IF(?,NOW(),last_success_at),
             last_failure_at=IF(?,last_failure_at,NOW()),updated_by=?,updated_at=NOW()
             WHERE id=? AND provider_key='bale'",
            [$status,$status,json_encode($result,JSON_UNESCAPED_UNICODE),!empty($result['ok'])?1:0,!empty($result['ok'])?1:0,$actorId,$id]
        );
        $this->audit('configuration_tested',$actorId,null,['id'=>$id,'ok'=>!empty($result['ok'])]);
    }

    public function setState(int $id, string $action, int $actorId): void
    {
        $row = $this->find($id);
        if (!$row) throw new \InvalidArgumentException('تنظیم بله پیدا نشد.');
        if ($action === 'activate' && (!in_array($row['status'],['verified','active'],true) || empty($row['last_success_at']))) {
            throw new \RuntimeException('پیش از فعال‌سازی بازو، ارسال پیام آزمایشی موفق الزامی است.');
        }
        if ($action === 'activate') {
            $eventCount=\Model::fetch("SELECT COUNT(*) c FROM proma_connect_provider_event_mappings WHERE provider_key='bale' AND provider_account_id=? AND is_enabled=1",[$id]);
            if((int)($eventCount['c']??0)<1)throw new \RuntimeException('حداقل یک رویداد قابل ارسال باید انتخاب شود.');
            if(!array_filter(json_decode((string)$row['capabilities_json'],true)?:[]))throw new \RuntimeException('حداقل یک نوع پیام باید فعال باشد.');
        }
        if ($action === 'default' && (!(int)$row['is_active'] || !in_array($row['status'],['verified','active'],true))) {
            throw new \RuntimeException('تنها بازوی فعال و آزموده‌شده می‌تواند پیش‌فرض باشد.');
        }
        if ($action === 'activate') \Model::execute("UPDATE proma_connect_provider_configs SET is_active=1,status='active',updated_at=NOW() WHERE id=?",[$id]);
        elseif ($action === 'disable') \Model::execute("UPDATE proma_connect_provider_configs SET is_active=0,is_default=0,status='disabled',updated_at=NOW() WHERE id=?",[$id]);
        elseif ($action === 'default') {
            \Model::begin();
            try {
                \Model::execute("UPDATE proma_connect_provider_configs SET is_default=0 WHERE provider_key='bale' AND environment=?",[$row['environment']]);
                \Model::execute('UPDATE proma_connect_provider_configs SET is_default=1 WHERE id=?',[$id]);
                \Model::commit();
            } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
        } elseif($action==='archive') {
            \Model::execute("UPDATE proma_connect_provider_configs SET is_active=0,is_default=0,status='archived',archived_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND provider_key='bale'",[$actorId,$id]);
        } elseif($action==='remove_token') {
            \Model::execute("UPDATE proma_connect_provider_configs SET encrypted_secret=NULL,is_active=0,is_default=0,status='configured_not_verified',updated_by=?,updated_at=NOW() WHERE id=? AND provider_key='bale'",[$actorId,$id]);
        } else throw new \InvalidArgumentException('عملیات معتبر نیست.');
        $this->audit('configuration_'.$action,$actorId,$row,$this->find($id));
    }

    private function normalizeBotId(string $value): string
    {
        $value = trim(to_english_digits(strtr($value,['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'])));
        if (!preg_match('/^[1-9]\d{0,29}$/', $value)) throw new \InvalidArgumentException('شناسه عددی بازو معتبر نیست.');
        return $value;
    }
    private function audit(string $action,int $actor,$before,$after): void
    {
        \Model::execute(
            'INSERT INTO proma_connect_settings_audit
             (section_key,action_key,actor_id,before_json,after_json,request_id,created_at)
             VALUES (?,?,?,?,?,?,NOW())',
            ['bale',$action,$actor,$this->safe($before),$this->safe($after),\ErrorHandler::requestId()]
        );
    }
    private function safe($value): ?string
    {
        if (!$value) return null;
        if (is_array($value)) unset($value['api_key'],$value['encrypted_secret']);
        return json_encode($value,JSON_UNESCAPED_UNICODE);
    }
    private function uuid(): string
    {
        $data=random_bytes(16);$data[6]=chr((ord($data[6])&15)|64);$data[8]=chr((ord($data[8])&63)|128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));
    }
}
