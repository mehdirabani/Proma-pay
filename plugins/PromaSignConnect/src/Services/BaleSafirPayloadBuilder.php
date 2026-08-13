<?php
namespace Proma\Plugins\SignConnect\Services;
final class BaleSafirPayloadBuilder
{
    public function build(array $message,string $requestId,string $botId):array
    {
        if(!preg_match('/^\d+$/',$botId))throw new \InvalidArgumentException('شناسه بازوی بله باید عددی باشد.');
        $type=(string)($message['type']??'text');$data=[];
        if($type==='otp'){$otp=(string)($message['otp']??$message['variables']['code']??'');if(!preg_match('/^\d{4,8}$/',$otp))throw new \InvalidArgumentException('OTP نامعتبر است.');$data=['otp_message'=>['otp'=>$otp]];}
        elseif(in_array($type,['template','secure_template'],true)){$id=trim((string)($message['template_id']??''));if($id==='')throw new \InvalidArgumentException('شناسه قالب بله الزامی است.');$fields=$message['text_fields']??[];if(!is_array($fields))throw new \InvalidArgumentException('نگاشت قالب معتبر نیست.');$data=['template'=>['template_id'=>$id,'text_fields'=>$fields]];if($type==='secure_template')$data['is_secure']=true;}
        else{$text=trim((string)($message['text']??''));if($text===''||mb_strlen($text,'UTF-8')>4000)throw new \InvalidArgumentException('متن پیام بله خالی یا بیش از حد مجاز است.');$data=['message'=>['text'=>$text]];if($type==='secure')$data['is_secure']=true;if(!empty($message['buttons']))$data['inline_keyboard']=$this->buttons($message['buttons']);}
        return ['request_id'=>$requestId,'bot_id'=>(int)$botId,'phone_number'=>IranianPhoneNumberNormalizer::normalize((string)($message['to']??'')),'message_data'=>$data];
    }
    private function buttons(array $buttons):array{$safe=[];foreach(array_slice($buttons,0,4)as$b){$url=(string)($b['url']??'');if($url!==''&&!preg_match('#^https://#i',$url))throw new \InvalidArgumentException('نشانی دکمه باید HTTPS باشد.');$safe[]=['text'=>mb_substr((string)($b['text']??''),0,50,'UTF-8'),'url'=>$url];}return [$safe];}
}
