<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\ProviderTestService;
final class ProviderTestController extends \Controller
{
    public function send($provider):void{$this->onlyPost();$provider=preg_replace('/[^a-z]/','',(string)$provider);try{$result=(new ProviderTestService())->send($provider,$_POST,(int)\Auth::id());\set_flash($result['ok']?'success':'error',$result['ok']?'پیام آزمایشی پذیرفته شد. شناسه پیگیری: '.$result['request_id']:'ارائه‌دهنده پیام آزمایشی را نپذیرفت. شناسه پیگیری: '.$result['request_id']);}catch(\InvalidArgumentException|\RuntimeException$e){\set_flash('error',$e->getMessage());}catch(\Throwable$e){\set_flash('error','ارسال آزمایشی انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}\redirect('plugin/sign-connect/settings/'.$provider);}
}
