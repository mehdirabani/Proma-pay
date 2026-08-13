<?php
namespace Proma\Plugins\SignConnect\Services;

final class ModulePolicyService
{
    private const SCHEMAS = [
        'mfa' => [
            'mode'=>['enum',['disabled','optional','required_by_role','required_for_sensitive_actions','required_on_untrusted_device','globally_required']],
            'roles'=>['list',['admin','operator','lawyer','customer']],
            'sensitive_actions'=>['bool'], 'trusted_device_days'=>['int',1,90],
            'max_trusted_devices'=>['int',1,20],
            'primary_channel'=>['enum',['ippanel','smsir','bale','telegram']],
            'fallback_channel'=>['enum',['ippanel','smsir','bale','telegram','recovery_code']],
            'step_up_minutes'=>['int',2,60],
        ],
        'otp-security' => [
            'code_length'=>['int',4,8], 'ttl_seconds'=>['int',60,600],
            'resend_seconds'=>['int',30,3600], 'max_attempts'=>['int',3,10],
            'hourly_limit'=>['int',1,30], 'daily_limit'=>['int',1,100],
            'ip_hourly_limit'=>['int',5,200], 'active_challenge_limit'=>['int',1,5],
            'lock_seconds'=>['int',60,86400], 'invalidate_previous'=>['bool'],
            'replay_protection'=>['bool'],
        ],
        'hashes' => [
            'algorithm'=>['enum',['sha256']], 'hmac_algorithm'=>['enum',['sha256']],
            'canonicalization'=>['enum',['json-c14n-v1']],
            'evidence_schema'=>['enum',['1']], 'active_key_version'=>['int',1,999],
            'fingerprint_length'=>['int',12,32], 'scheduled_audit'=>['bool'],
        ],
        'document-versions' => [
            'auto_first_version'=>['bool'], 'new_on_relevant_change'=>['bool'],
            'stale_detection'=>['bool'], 'duplicate_prevention'=>['bool'],
            'protect_finalized'=>['bool'],
            'customer_visible'=>['enum',['published','finalized','none']],
            'archive_drafts_days'=>['int',30,3650],
        ],
        'evidence-retention' => [
            'otp_audit_days'=>['int',30,3650], 'delivery_success_days'=>['int',30,3650],
            'delivery_failed_days'=>['int',30,3650], 'webhook_days'=>['int',7,3650],
            'temporary_link_days'=>['int',1,90], 'draft_days'=>['int',30,3650],
            'archive_before_delete'=>['bool'], 'batch_size'=>['int',10,500],
            'legal_hold_required'=>['bool'],
        ],
    ];

    public function get(string $section): array
    {
        $this->schema($section);
        $row = \Model::fetch('SELECT * FROM proma_connect_module_policies WHERE section_key=? LIMIT 1',[$section]);
        $policy = $row ? json_decode((string)$row['policy_json'],true) : [];
        return ['enabled'=>(bool)($row['is_enabled']??false),'version'=>(int)($row['policy_version']??1),'policy'=>is_array($policy)?$policy:[]];
    }

    public function save(string $section,array $input,int $actorId): void
    {
        $schema=$this->schema($section);$policy=[];
        foreach($schema as$key=>$rule)$policy[$key]=$this->value($key,$rule,$input[$key]??null);
        if($section==='mfa'&&$policy['mode']!=='disabled'){
            $settings=(new SettingsService())->all(false);
            if(($settings['provider_'.$policy['primary_channel'].'_enabled']??'0')!=='1'&&$policy['primary_channel']!=='in_app'){
                throw new \InvalidArgumentException('کانال اصلی MFA فعال و قابل استفاده نیست.');
            }
            if($policy['primary_channel']==='telegram'&&$policy['fallback_channel']==='telegram'){
                throw new \InvalidArgumentException('تلگرام نمی‌تواند تنها مسیر اجباری MFA باشد.');
            }
        }
        \Model::begin();
        try{
            $before=$this->get($section);
            \Model::execute(
                'INSERT INTO proma_connect_module_policies
                 (section_key,policy_version,policy_json,is_enabled,updated_by,updated_at)
                 VALUES (?,1,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE policy_version=policy_version+1,
                 policy_json=VALUES(policy_json),is_enabled=VALUES(is_enabled),
                 updated_by=VALUES(updated_by),updated_at=NOW()',
                [$section,json_encode($policy,JSON_UNESCAPED_UNICODE),!empty($input['enabled'])?1:0,$actorId]
            );
            \Model::execute(
                'INSERT INTO proma_connect_settings_audit
                 (section_key,action_key,actor_id,before_json,after_json,request_id,created_at)
                 VALUES (?,?,?,?,?,?,NOW())',
                [$section,'policy_saved',$actorId,json_encode($before),json_encode(['enabled'=>!empty($input['enabled']),'policy'=>$policy]),\ErrorHandler::requestId()]
            );
            \Model::commit();
        }catch(\Throwable$e){\Model::rollBack();throw$e;}
    }

    private function schema(string$section):array
    {
        if(!isset(self::SCHEMAS[$section]))throw new \InvalidArgumentException('بخش سیاست معتبر نیست.');
        return self::SCHEMAS[$section];
    }
    private function value(string$key,array$rule,$raw)
    {
        if($rule[0]==='bool')return!empty($raw);
        if($rule[0]==='int'){if(!ctype_digit((string)$raw)||(int)$raw<$rule[1]||(int)$raw>$rule[2])throw new \InvalidArgumentException('مقدار «'.$key.'» خارج از محدوده مجاز است.');return(int)$raw;}
        if($rule[0]==='enum'){if(!in_array((string)$raw,$rule[1],true))throw new \InvalidArgumentException('مقدار «'.$key.'» معتبر نیست.');return(string)$raw;}
        if($rule[0]==='list'){$values=is_array($raw)?$raw:[];foreach($values as$v)if(!in_array($v,$rule[1],true))throw new \InvalidArgumentException('مقدار فهرست نقش‌ها معتبر نیست.');return array_values(array_unique($values));}
        throw new \LogicException('policy_schema_invalid');
    }
}

