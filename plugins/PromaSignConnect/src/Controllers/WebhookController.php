<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\{SettingsService,TelegramLinkTokenService,ProviderRegistry};
final class WebhookController extends \Controller
{
    public function telegram(){
        $settings=(new SettingsService())->all(true);$provided=(string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'');if(($settings['telegram_webhook_secret']??'')===''||!hash_equals($settings['telegram_webhook_secret'],$provided))\ErrorHandler::abort(403);
        $raw=file_get_contents('php://input',false,null,0,131073);if(strlen($raw)>131072)\ErrorHandler::abort(413);$payload=json_decode($raw,true);if(!is_array($payload))\ErrorHandler::abort(422);$update=(string)($payload['update_id']??'');if(!preg_match('/^\d+$/',$update))\ErrorHandler::abort(422);
        try{\Model::execute('INSERT INTO proma_connect_webhook_events (provider_key,external_event_id,payload_hash,created_at) VALUES (\'telegram\',?,?,NOW())',[$update,hash('sha256',$raw)]);}catch(\Throwable$e){$this->json(['ok'=>true,'duplicate'=>true]);}
        $message=$payload['message']??null;if(is_array($message)&&preg_match('/^\\/start(?:@\\w+)?\\s+([A-Za-z0-9_-]{20,64})$/',(string)($message['text']??''),$match)){try{$from=$message['from']??[];$link=(new TelegramLinkTokenService())->consume($match[1],['user_id'=>(string)($from['id']??''),'chat_id'=>(string)($message['chat']['id']??''),'username'=>$from['username']??null,'display_name'=>trim((string)($from['first_name']??'').' '.(string)($from['last_name']??'')),'language_code'=>$from['language_code']??null],(string)($settings['telegram_bot_id']??''));if(class_exists('\\SystemOutbox'))\SystemOutbox::safeEnqueueNotification($link['user_id'],'اتصال تلگرام','حساب تلگرام شما با موفقیت متصل شد.','security',\url('plugin/sign-connect/settings/telegram'),'telegram_link',$link['user_id']);}catch(\Throwable$e){}}
        \Model::execute('UPDATE proma_connect_webhook_events SET processed_at=NOW() WHERE provider_key=\'telegram\' AND external_event_id=?',[$update]);$this->json(['ok'=>true]);
    }
}
