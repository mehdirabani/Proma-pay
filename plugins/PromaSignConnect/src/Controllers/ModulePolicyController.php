<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\ModulePolicyService;
use Proma\Plugins\SignConnect\Services\SignerPolicyService;
final class ModulePolicyController extends \Controller
{
    public function save($section):void{$this->onlyPost();$section=(string)$section;try{if($section==='signers')(new SignerPolicyService())->save($_POST,(int)\Auth::id());else(new ModulePolicyService())->save($section,$_POST,(int)\Auth::id());\set_flash('success','سیاست این بخش ذخیره و نسخه جدید آن ثبت شد.');}catch(\InvalidArgumentException|\RuntimeException$e){\set_flash('error',$e->getMessage());}catch(\Throwable$e){\set_flash('error','عملیات انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}\redirect('plugin/sign-connect/settings/'.$section);}
}

