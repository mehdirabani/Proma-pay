<?php
namespace Proma\Plugins\SignConnect\Services;
use Proma\Plugins\SignConnect\Providers\{IPPanelProvider,SmsIrProvider,BaleSafirProvider,TelegramProvider,InAppProvider};
final class ProviderRegistry
{
    private array $settings;
    public function __construct(?array $settings = null) { $this->settings = $settings ?: (new SettingsService())->all(true); }
    public function get(string $key, array $message = [])
    {
        if (($this->settings['provider_'.$key.'_enabled']??'0')!=='1' && !in_array($key,['in_app','bale'],true)) throw new \RuntimeException('provider_disabled');
        $bale=null;
        if($key==='bale'){
            $event=preg_replace('/[^a-z0-9._-]/','',(string)($message['event_key']??$message['template_key']??''));
            if($event!==''&&!in_array($event,['custom_message','otp'],true)){
                $bale=\Model::fetch("SELECT c.* FROM proma_connect_provider_configs c JOIN proma_connect_provider_event_mappings m ON m.provider_account_id=c.id AND m.provider_key='bale' AND m.event_key=? AND m.is_enabled=1 WHERE c.provider_key='bale' AND c.is_active=1 AND c.status='active' AND c.archived_at IS NULL ORDER BY c.is_default DESC,c.id ASC LIMIT 1",[$event]);
                if(!$bale)throw new \RuntimeException('bale_event_not_permitted');
            }else{
                $bale=\Model::fetch("SELECT * FROM proma_connect_provider_configs WHERE provider_key='bale' AND is_active=1 AND status='active' AND archived_at IS NULL ORDER BY is_default DESC,id ASC LIMIT 1");
            }
            if(!$bale)throw new \RuntimeException('bale_configuration_unavailable');
            $required=(string)($message['capability']??'text_message');$caps=json_decode((string)($bale['capabilities_json']??'{}'),true)?:[];
            if(empty($caps[$required]))throw new \RuntimeException('bale_capability_not_permitted');
            $bale['api_key']=SecretCipher::decrypt((string)$bale['encrypted_secret']);
        }
        $map = [
            'ippanel'=>[IPPanelProvider::class,['api_key'=>$this->settings['ippanel_api_key']??'','from_number'=>$this->settings['ippanel_from_number']??'']],
            'smsir'=>[SmsIrProvider::class,['api_key'=>$this->settings['smsir_api_key']??'','sender_line'=>$this->settings['smsir_sender_line']??'']],
            'bale'=>[BaleSafirProvider::class,['api_key'=>$bale['api_key']??'','bot_id'=>$bale['external_bot_id']??'','base_url'=>$bale['api_base_url']??'https://safir.bale.ai/api/v3/send_message']],
            'telegram'=>[TelegramProvider::class,['bot_token'=>$this->settings['telegram_bot_token']??'']],
            'in_app'=>[InAppProvider::class,[]],
        ];
        if (!isset($map[$key])) throw new \InvalidArgumentException('unknown_provider');
        return new $map[$key][0]($map[$key][1]);
    }
    public function supports(string $provider, string $capability): bool
    {
        if ($provider === 'in_app') return ($this->settings['provider_in_app_enabled'] ?? '1') === '1';
        if ($provider === 'bale') {
            $rows = \Model::fetchAll("SELECT capabilities_json FROM proma_connect_provider_configs WHERE provider_key='bale' AND is_active=1 AND status='active' AND archived_at IS NULL ORDER BY is_default DESC,id ASC");
            foreach($rows as$row){$capabilities=json_decode((string)($row['capabilities_json']??'{}'),true)?:[];if(!empty($capabilities[$capability]))return true;}return false;
        }
        if (($this->settings['provider_' . $provider . '_enabled'] ?? '0') !== '1') return false;
        return in_array($capability, ProviderCapabilityCatalog::normalize(
            $provider,
            $this->settings['provider_' . $provider . '_capabilities'] ?? ''
        ), true);
    }
}
