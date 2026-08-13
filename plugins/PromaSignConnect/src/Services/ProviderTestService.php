<?php
namespace Proma\Plugins\SignConnect\Services;
use Proma\Plugins\SignConnect\Providers\{IPPanelProvider,SmsIrProvider,TelegramProvider};
final class ProviderTestService
{
    public function send(string $provider,array $input,int $actorId):array
    {
        if(!in_array($provider,['ippanel','smsir','telegram'],true))throw new \InvalidArgumentException('ارائه‌دهنده تست معتبر نیست.');
        $settings=(new SettingsService())->all(true);$text=trim((string)($input['test_message']??''));
        if($text===''||mb_strlen($text)>500)throw new \InvalidArgumentException('متن آزمایشی باید بین ۱ تا ۵۰۰ نویسه باشد.');
        $mode=(string)($input['send_mode']??'text');$pattern=trim((string)($input['pattern']??''));$recipient='';
        if($provider==='ippanel'){$recipient=IranianPhoneNumberNormalizer::normalize((string)($input['test_mobile']??''));$adapter=new IPPanelProvider(['api_key'=>$settings['ippanel_api_key']??'','from_number'=>$settings['ippanel_from_number']??'']);if($mode==='pattern'&&$pattern==='')throw new \InvalidArgumentException('کد پترن IPPanel الزامی است.');}
        elseif($provider==='smsir'){$recipient=IranianPhoneNumberNormalizer::normalize((string)($input['test_mobile']??''));$adapter=new SmsIrProvider(['api_key'=>$settings['smsir_api_key']??'','sender_line'=>$settings['smsir_sender_line']??'']);if($mode==='verify'&&(!ctype_digit($pattern)||$pattern===''))throw new \InvalidArgumentException('Template ID عددی SMS.ir الزامی است.');}
        else{$userId=(int)($input['test_user_id']??0);if($userId<1)throw new \InvalidArgumentException('شناسه کاربر متصل به تلگرام الزامی است.');$link=\Model::fetch("SELECT chat_id FROM proma_connect_telegram_links WHERE user_id=? AND bot_id=? AND status='active' AND notifications_enabled=1 LIMIT 1",[$userId,(string)($settings['telegram_bot_id']??'')]);if(!$link)throw new \InvalidArgumentException('برای این کاربر اتصال فعال تلگرام پیدا نشد.');$recipient=(string)$link['chat_id'];$adapter=new TelegramProvider(['bot_token'=>$settings['telegram_bot_token']??'']);}
        $requestId=$this->uuid();$started=microtime(true);$result=[];$error=null;
        try{$payload=['to'=>$recipient,'text'=>$text,'request_id'=>$requestId];if($provider==='telegram')$payload['chat_id']=$recipient;if($mode!=='text'){$payload['pattern']=$pattern;$payload['variables']=$this->variables((string)($input['variables']??''));}$result=$adapter->send($payload);if(empty($result['ok']))$error='provider_rejected';}
        catch(\Throwable$e){$error=substr($e->getMessage(),0,100);$result=['ok'=>false];}
        $duration=(int)round((microtime(true)-$started)*1000);
        \Model::execute("INSERT INTO proma_connect_provider_test_deliveries (request_id,provider_key,recipient_hash,send_mode,external_message_id,status,duration_ms,error_code,tested_by,tested_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",[$requestId,$provider,hash('sha256',$recipient),$mode,$result['external_id']??null,!empty($result['ok'])?'accepted':'failed',$duration,$error,$actorId]);
        \Model::execute("INSERT INTO proma_connect_settings_audit (section_key,action_key,actor_id,after_json,request_id,created_at) VALUES (?,'provider_test_sent',?,?,?,NOW())",[$provider,$actorId,json_encode(['ok'=>!empty($result['ok']),'mode'=>$mode,'duration_ms'=>$duration]),$requestId]);
        return['ok'=>!empty($result['ok']),'request_id'=>$requestId,'external_id'=>$result['external_id']??null,'duration_ms'=>$duration];
    }
    private function variables(string $json):array{$data=json_decode($json,true);if(!is_array($data))throw new \InvalidArgumentException('متغیرهای تست باید JSON معتبر باشند.');foreach($data as$key=>$value)if(!preg_match('/^[a-z][a-z0-9_]{1,79}$/',(string)$key)||!is_scalar($value))throw new \InvalidArgumentException('ساختار متغیرهای تست معتبر نیست.');return$data;}
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
