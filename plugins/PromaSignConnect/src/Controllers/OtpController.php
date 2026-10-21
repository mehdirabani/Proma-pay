<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\{OtpService,DispatchService};
final class OtpController extends \Controller
{
    public function request(){$this->onlyPost();try{$purpose=preg_replace('/[^a-z0-9._-]/i','',(string)($_POST['purpose']??'login'));$challenge=(new OtpService())->create((string)($_POST['mobile']??''),$purpose,\Auth::id());(new DispatchService())->enqueue(['idempotency_key'=>'otp:'.$challenge['id'],'provider'=>'auto','channel'=>'sms','to'=>$challenge['mobile'],'kind'=>'otp','template_key'=>'otp','variables'=>['code'=>$challenge['code']]]);$this->json(['ok'=>true,'challenge_id'=>$challenge['id'],'message'=>'اگر حساب معتبر باشد، کد ارسال می‌شود.']);}catch(\Throwable $e){$this->json(['ok'=>true,'message'=>'اگر حساب معتبر باشد، کد ارسال می‌شود.']);}}
    public function verify(){$this->onlyPost();$ok=(new OtpService())->verify((string)($_POST['challenge_id']??''),(string)($_POST['code']??''),(string)($_POST['purpose']??'login'));$this->json(['ok'=>$ok],$ok?200:422);}
}
