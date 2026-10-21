<?php
namespace Proma\Plugins\SignConnect\Services;

final class SignerPolicyService
{
    private const DOCUMENTS=['contract','amendment','guarantee','settlement','legal_notice','custom'];
    private const ROLES=['customer','company_representative','guarantor_1','guarantor_2','witness','seller','authorized_manager','legal_representative','custom'];
    public function all():array{return \Model::fetchAll('SELECT * FROM proma_sign_signer_policies ORDER BY document_type,sequence_no,id LIMIT 200');}
    public function save(array$input,int$actorId):void
    {
        $document=(string)($input['document_type']??'');$role=(string)($input['signer_role']??'');
        if(!in_array($document,self::DOCUMENTS,true)||!in_array($role,self::ROLES,true))throw new \InvalidArgumentException('نوع سند یا نقش امضاکننده معتبر نیست.');
        $workflow=in_array(($input['workflow']??''),['sequential','parallel'],true)?$input['workflow']:'sequential';
        $sequence=max(1,min(20,(int)($input['sequence_no']??1)));$expires=max(1,min(8760,(int)($input['expires_hours']??72)));$reminder=max(1,min($expires,(int)($input['reminder_hours']??24)));
        \Model::begin();try{
            \Model::execute(
                'INSERT INTO proma_sign_signer_policies
                 (document_type,signer_role,is_required,workflow,sequence_no,otp_required,
                  drawn_signature_required,typed_name_required,expires_hours,reminder_hours,
                  is_active,updated_by,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE is_required=VALUES(is_required),workflow=VALUES(workflow),
                 sequence_no=VALUES(sequence_no),otp_required=VALUES(otp_required),
                 drawn_signature_required=VALUES(drawn_signature_required),
                 typed_name_required=VALUES(typed_name_required),expires_hours=VALUES(expires_hours),
                 reminder_hours=VALUES(reminder_hours),is_active=1,updated_by=VALUES(updated_by),updated_at=NOW()',
                [$document,$role,!empty($input['is_required'])?1:0,$workflow,$sequence,!empty($input['otp_required'])?1:0,!empty($input['drawn_signature_required'])?1:0,!empty($input['typed_name_required'])?1:0,$expires,$reminder,$actorId]
            );
            \Model::execute("INSERT INTO proma_connect_settings_audit (section_key,action_key,actor_id,after_json,request_id,created_at) VALUES ('signers','policy_saved',?,?,?,NOW())",[$actorId,json_encode(['document_type'=>$document,'signer_role'=>$role]),\ErrorHandler::requestId()]);
            \Model::commit();
        }catch(\Throwable$e){\Model::rollBack();throw$e;}
    }
}

