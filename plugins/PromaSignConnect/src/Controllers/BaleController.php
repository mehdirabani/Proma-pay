<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\{BaleAccountService,ProviderEventMappingService,IranianPhoneNumberNormalizer};
use Proma\Plugins\SignConnect\Providers\BaleSafirProvider;
final class BaleController extends \Controller
{
    public function save():void
    {
        $this->onlyPost();
        try{$id=(new BaleAccountService())->save($_POST,(int)\Auth::id());\set_flash('success','تنظیمات بازوی بله ذخیره شد.');\redirect('plugin/sign-connect/settings/bale',['edit'=>$id]);}
        catch(\InvalidArgumentException|\RuntimeException$e){\set_flash('error',$e->getMessage());}
        catch(\Throwable$e){\set_flash('error','ذخیره تنظیمات انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}
        \redirect('plugin/sign-connect/settings/bale');
    }
    public function permissions($accountId):void
    {
        $this->onlyPost();
        try{$count=(new ProviderEventMappingService())->saveForProviderAccount('bale',(int)$accountId,is_array($_POST['events']??null)?$_POST['events']:[],(int)\Auth::id());\set_flash('success',$count.' دسترسی رویداد برای این بازو ذخیره شد.');}
        catch(\InvalidArgumentException|\RuntimeException$e){\set_flash('error',$e->getMessage());}
        catch(\Throwable$e){\set_flash('error','ذخیره دسترسی‌ها انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}
        \redirect('plugin/sign-connect/settings/bale',['edit'=>(int)$accountId]);
    }
    public function test($accountId):void
    {
        $this->onlyPost();$service=new BaleAccountService();
        try{
            $account=$service->find((int)$accountId,true);
            if(!$account||empty($account['api_key']))throw new \RuntimeException('توکن وب‌سرویس بله ذخیره نشده است.');
            $requestId=preg_match('/^[0-9a-f-]{36}$/i',(string)($_POST['request_id']??''))?(string)$_POST['request_id']:$this->uuid();
            $message=trim((string)($_POST['test_message']??'این یک پیام آزمایشی از سامانه پرما پی است.'));
            if($message===''||mb_strlen($message)>500)throw new \InvalidArgumentException('متن آزمایشی باید بین ۱ تا ۵۰۰ نویسه باشد.');
            $type=in_array((string)($_POST['message_type']??'text'),['text','secure'],true)?(string)($_POST['message_type']??'text'):'text';
            $provider=new BaleSafirProvider(['api_key'=>$account['api_key'],'bot_id'=>$account['external_bot_id'],'base_url'=>$account['api_base_url']]);
            $result=$provider->send(['type'=>$type,'to'=>(string)($_POST['test_mobile']??''),'text'=>$message,'request_id'=>$requestId]);
            \Model::execute("INSERT INTO proma_connect_bale_attempts (bale_account_id,attempt_uuid,request_id,bot_id,masked_recipient,message_type,message_id,status,provider_error_name,is_retryable,duration_ms,attempted_at,completed_at,response_snapshot_redacted) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),?)",[(int)$accountId,$this->uuid(),$requestId,$account['external_bot_id'],IranianPhoneNumberNormalizer::mask(IranianPhoneNumberNormalizer::normalize((string)$_POST['test_mobile'])),$type,$result['external_id']??null,!empty($result['ok'])?'accepted':'failed',$result['error']['name']??null,!empty($result['error']['retryable'])?1:0,$result['duration_ms']??null,json_encode($result['response']??[])]);
            $service->markTest((int)$accountId,$result,(int)\Auth::id());
            \set_flash(!empty($result['ok'])?'success':'error',!empty($result['ok'])?'پیام آزمایشی پذیرفته شد؛ اکنون می‌توانید بازو را فعال کنید.':$this->safeTestError($result));
        }catch(\InvalidArgumentException|\RuntimeException$e){$service->markTest((int)$accountId,['ok'=>false,'error'=>'test_failed'],(int)\Auth::id());\set_flash('error',$e->getMessage());}
        catch(\Throwable$e){$service->markTest((int)$accountId,['ok'=>false,'error'=>'unexpected'],(int)\Auth::id());\set_flash('error','ارسال آزمایشی انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}
        \redirect('plugin/sign-connect/settings/bale',['edit'=>(int)$accountId]);
    }
    public function state($accountId):void
    {
        $this->onlyPost();try{(new BaleAccountService())->setState((int)$accountId,(string)($_POST['action']??''),(int)\Auth::id());\set_flash('success','وضعیت بازوی بله تغییر کرد.');}catch(\InvalidArgumentException|\RuntimeException$e){\set_flash('error',$e->getMessage());}catch(\Throwable$e){\set_flash('error','تغییر وضعیت انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}\redirect('plugin/sign-connect/settings/bale');
    }
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
    private function safeTestError(array$result):string{$category=$result['error']['category']??'';$map=['invalid_payload'=>'ساختار درخواست ارسال پیام معتبر نیست.','invalid_recipient'=>'شماره آزمایشی معتبر نیست.','channel_unavailable'=>'این شماره عضو بله نیست.','provider_credit_problem'=>'اعتبار سرویس بله کافی نیست.','provider_account_limit'=>'محدودیت حساب سرویس بله فعال شده است.','retryable_after_delay'=>'محدودیت موقت ارسال سرویس بله فعال شده است.','retryable'=>'ارتباط با سرویس بله موقتاً ناموفق بود.'];return$map[$category]??'سرویس بله پیام آزمایشی را نپذیرفت.';}
}
