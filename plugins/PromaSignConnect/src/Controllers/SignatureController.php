<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\SignatureService;
final class SignatureController extends \Controller
{
    public function show($requestId){$request=\Model::fetch('SELECT r.*,d.rendered_title,d.rendered_body FROM proma_sign_requests r JOIN contract_document_versions d ON d.id=r.document_version_id WHERE r.public_id=?',[(string)$requestId]);if(!$request)\ErrorHandler::abort(404);$this->render('plugin:proma-sign-connect/sign',['title'=>'تأیید و امضای الکترونیکی داخلی','request'=>$request]);}
    public function complete($requestId){$this->onlyPost();try{$result=(new SignatureService())->complete((string)$requestId,(int)\Auth::id(),(string)($_POST['typed_name']??''),(string)($_POST['otp_id']??''),(string)($_POST['otp_code']??''),['accepted'=>!empty($_POST['accepted']),'ip'=>$_SERVER['REMOTE_ADDR']??'','user_agent'=>$_SERVER['HTTP_USER_AGENT']??'']);\set_flash('success','امضای داخلی ثبت شد. شناسه شاهد: '.$result['evidence_public_id']);}catch(\Throwable $e){\set_flash('error','ثبت امضا انجام نشد: '.$e->getMessage());}\redirect('plugin/sign-connect/sign/'.$requestId);}
    public function verify($publicId){$this->render('plugin:proma-sign-connect/verify',['title'=>'اعتبارسنجی سند','result'=>(new SignatureService())->verifyPublic((string)$publicId)],'public');}
}
