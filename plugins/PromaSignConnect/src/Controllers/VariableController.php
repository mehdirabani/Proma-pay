<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\TemplateVariableRegistry;
final class VariableController extends \Controller
{
    public function save():void{$this->onlyPost();try{(new TemplateVariableRegistry())->save($_POST,(int)\Auth::id());\set_flash('success','متغیر و نگاشت ارائه‌دهندگان ذخیره شد.');}catch(\InvalidArgumentException$e){\set_flash('error',$e->getMessage());}catch(\Throwable$e){\set_flash('error','ذخیره متغیر انجام نشد. کد پیگیری: '.\ErrorHandler::requestId());}\redirect('plugin/sign-connect/settings/variables');}
}
