<?php
namespace Proma\Plugins\SignConnect\Services;

final class ProviderEventMappingService
{
    private const MODES = [
        'ippanel' => ['disabled', 'text', 'pattern'],
        'smsir' => ['disabled', 'text', 'verify'],
        'bale' => ['disabled', 'text', 'secure', 'template'],
        'telegram' => ['disabled', 'text'],
    ];

    public function forProvider(string $provider): array
    {
        $this->assertProvider($provider);
        $rows = \Model::fetchAll(
            'SELECT * FROM proma_connect_provider_event_mappings
             WHERE provider_key=? AND provider_account_id IS NULL ORDER BY event_key',
            [$provider]
        );
        $map = [];
        foreach ($rows as $row) $map[$row['event_key']] = $row;
        return $map;
    }

    public function forProviderAccount(string $provider, int $accountId): array
    {
        $this->assertProvider($provider);
        if ($accountId < 1) throw new \InvalidArgumentException('شناسه حساب ارائه‌دهنده معتبر نیست.');
        $rows = \Model::fetchAll(
            'SELECT * FROM proma_connect_provider_event_mappings WHERE provider_key=? AND provider_account_id=? ORDER BY event_key',
            [$provider,$accountId]
        );
        $map=[]; foreach($rows as $row) $map[$row['event_key']]=$row;
        return $map;
    }

    public function saveForProviderAccount(string $provider, int $accountId, array $rows, int $actorId): int
    {
        $this->assertProvider($provider);
        $account=\Model::fetch('SELECT id,capabilities_json,archived_at FROM proma_connect_provider_configs WHERE id=? AND provider_key=? LIMIT 1',[$accountId,$provider]);
        if(!$account||!empty($account['archived_at'])) throw new \InvalidArgumentException('حساب ارائه‌دهنده فعال پیدا نشد.');
        $capabilities=json_decode((string)$account['capabilities_json'],true)?:[];
        $catalog=new NotificationEventCatalog(); $saved=0;
        \Model::begin();
        try {
            \Model::execute('DELETE FROM proma_connect_provider_event_mappings WHERE provider_key=? AND provider_account_id=?',[$provider,$accountId]);
            foreach($rows as $eventKey=>$input){
                $definition=$catalog->find((string)$eventKey);
                if(!$definition||!is_array($input)||empty($input['enabled'])) continue;
                $mode=(string)($input['mode']??'text');
                if(!in_array($mode,self::MODES[$provider],true)||$mode==='disabled') throw new \InvalidArgumentException('نوع ارسال رویداد معتبر نیست.');
                $required=$this->requiredCapability((string)$eventKey,$mode);
                if(empty($capabilities[$required])) throw new \InvalidArgumentException('رویداد «'.$definition['title_fa'].'» به قابلیت غیرفعال وابسته است.');
                $external=trim((string)($input['external_template_id']??''));
                if($mode==='template'&&$external==='') throw new \InvalidArgumentException('شناسه قالب برای رویداد قالبی الزامی است.');
                \Model::execute(
                    "INSERT INTO proma_connect_provider_event_mappings (provider_key,provider_account_id,event_key,is_enabled,send_mode,external_template_id,variable_mapping_json,template_body,validation_state,template_version,updated_by,updated_at) VALUES (?,?,?,1,?,?,? ,?,'valid',1,?,NOW())",
                    [$provider,$accountId,(string)$eventKey,$mode,$external!==''?$external:null,$definition['variables_json'],trim((string)($input['body']??$definition['default_text'])),$actorId]
                );
                $saved++;
            }
            if($saved<1) throw new \InvalidArgumentException('حداقل یک رویداد قابل ارسال باید انتخاب شود.');
            $this->audit($provider.'#'.$accountId,$actorId,$saved); \Model::commit();
        } catch(\Throwable $e){\Model::rollBack();throw $e;}
        return $saved;
    }

    public function save(string $provider, array $rows, int $actorId): int
    {
        $this->assertProvider($provider);
        $catalog = new NotificationEventCatalog();
        $saved = 0;
        \Model::begin();
        try {
            foreach ($rows as $eventKey => $input) {
                if (!$catalog->find((string) $eventKey) || !is_array($input)) continue;
                $mode = (string) ($input['mode'] ?? 'disabled');
                if (!in_array($mode, self::MODES[$provider], true)) {
                    throw new \InvalidArgumentException('نوع ارسال برای این ارائه‌دهنده معتبر نیست.');
                }
                $externalId = trim((string) ($input['external_template_id'] ?? ''));
                if (in_array($mode, ['pattern', 'verify'], true) && $externalId === '') {
                    throw new \InvalidArgumentException('کد پترن یا شناسه قالب برای حالت انتخاب‌شده الزامی است.');
                }
                if ($provider === 'smsir' && $mode === 'verify' && !ctype_digit($externalId)) {
                    throw new \InvalidArgumentException('Template ID پیامک باید عددی باشد.');
                }
                $body = trim((string) ($input['body'] ?? ''));
                if ($mode !== 'disabled' && $body === '') {
                    throw new \InvalidArgumentException('متن نمونه/قالب نمی‌تواند خالی باشد.');
                }
                $variables = $this->parseVariables((string) ($input['variables'] ?? ''));
                \Model::execute(
                    "INSERT INTO proma_connect_provider_event_mappings
                     (provider_key,provider_account_id,event_key,is_enabled,send_mode,
                      external_template_id,variable_mapping_json,template_body,
                      validation_state,template_version,updated_by,updated_at)
                     VALUES (?,NULL,?,?,?,?,?,?,'unverified',1,?,NOW())
                     ON DUPLICATE KEY UPDATE
                       is_enabled=VALUES(is_enabled),send_mode=VALUES(send_mode),
                       external_template_id=VALUES(external_template_id),
                       variable_mapping_json=VALUES(variable_mapping_json),
                       template_body=VALUES(template_body),
                       validation_state='unverified',
                       template_version=template_version+1,
                       updated_by=VALUES(updated_by),updated_at=NOW()",
                    [
                        $provider, (string) $eventKey, $mode === 'disabled' ? 0 : 1,
                        $mode, $externalId !== '' ? $externalId : null,
                        json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $body !== '' ? $body : null, $actorId,
                    ]
                );
                $saved++;
            }
            $this->audit($provider, $actorId, $saved);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        return $saved;
    }

    private function parseVariables(string $input): array
    {
        $allowed = TemplateVariableRegistry::keys();
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $input))) as $variable) {
            if (!in_array($variable, $allowed, true)) {
                throw new \InvalidArgumentException('متغیر قالب مجاز نیست: ' . $variable);
            }
            $result[] = $variable;
        }
        return array_values(array_unique($result));
    }

    private function assertProvider(string $provider): void
    {
        if (!isset(self::MODES[$provider])) throw new \InvalidArgumentException('ارائه‌دهنده معتبر نیست.');
    }

    private function requiredCapability(string $eventKey,string $mode): string
    {
        if($mode==='secure') return 'secure_message';
        if($mode==='template') return 'template_message';
        $map=['account_login_otp'=>'otp_message','signature_requested'=>'contract_signature_request','signature_reminder'=>'contract_signature_reminder','signature_completed'=>'contract_signed','installment_due_today'=>'installment_due'];
        return $map[$eventKey]??(in_array($eventKey,BaleCapabilityCatalog::keys(),true)?$eventKey:'text_message');
    }

    private function audit(string $provider, int $actorId, int $count): void
    {
        \Model::execute(
            "INSERT INTO proma_connect_settings_audit
             (section_key,action_key,actor_id,before_json,after_json,request_id,created_at)
             VALUES (?,'provider_event_mappings_saved',?,NULL,?,?,NOW())",
            [$provider, $actorId, json_encode(['count' => $count]), \ErrorHandler::requestId()]
        );
    }
}
