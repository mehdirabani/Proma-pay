<?php
namespace Proma\Plugins\SignConnect\Services;
final class SignatureService
{
    public function complete(string $publicId, int $userId, string $typedName, string $otpId, string $otpCode, array $client): array
    {
        if (trim($typedName)==='' || empty($client['accepted'])) throw new \InvalidArgumentException('acceptance_required');
        \Model::begin();
        try {
            if (!(new OtpService())->consumeForTransaction($otpId,$otpCode,'contract_sign')) throw new \InvalidArgumentException('invalid_otp');
            $request=\Model::fetch('SELECT r.*,d.checksum current_hash FROM proma_sign_requests r JOIN contract_document_versions d ON d.id=r.document_version_id WHERE r.public_id=? FOR UPDATE',[$publicId]);
            if(!$request||$request['status']!=='pending'||($request['expires_at']&&strtotime($request['expires_at'])<time()))throw new \RuntimeException('request_not_signable');
            if(!hash_equals($request['document_hash'],$request['current_hash']))throw new \RuntimeException('document_changed');
            $signer=\Model::fetch('SELECT * FROM proma_sign_signers WHERE request_id=? AND user_id=? FOR UPDATE',[(int)$request['id'],$userId]);
            if(!$signer||$signer['status']!=='pending')throw new \RuntimeException('signer_not_authorized_or_completed');
            if($request['workflow']==='sequential'){$prior=\Model::fetch("SELECT COUNT(*) c FROM proma_sign_signers WHERE request_id=? AND sequence_no<? AND status<>'signed'",[(int)$request['id'],(int)$signer['sequence_no']]);if((int)$prior['c']>0)throw new \RuntimeException('signing_order_violation');}
            $previous=\Model::fetch('SELECT evidence_hash FROM proma_sign_evidence WHERE request_id=? ORDER BY id DESC LIMIT 1',[(int)$request['id']]);
            $evidence=['request_id'=>(int)$request['id'],'signer_id'=>(int)$signer['id'],'document_hash'=>$request['document_hash'],'typed_name'=>trim($typedName),'account_user_id'=>$userId,'mobile_hash'=>$signer['mobile_hash'],'ip_hash'=>hash('sha256',(string)($client['ip']??'')),'user_agent_hash'=>hash('sha256',(string)($client['user_agent']??'')),'accepted_at'=>gmdate('c'),'mode'=>'internal_electronic_acceptance'];
            $canonical=json_encode($evidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$hash=hash('sha256',($previous['evidence_hash']??'').$canonical);$evidenceId=$this->uuid();
            \Model::execute('INSERT INTO proma_sign_evidence (public_id,request_id,signer_id,document_hash,evidence_json,evidence_hash,previous_evidence_hash,created_at) VALUES (?,?,?,?,?,?,?,NOW())',[$evidenceId,(int)$request['id'],(int)$signer['id'],$request['document_hash'],$canonical,$hash,$previous['evidence_hash']??null]);
            $registry=new SignatureHashRegistry();
            $registry->record((int)$request['id'],(int)$signer['id'],'document_sha256',$request['document_hash'],['document_version_id'=>(int)$request['document_version_id']]);
            $registry->record((int)$request['id'],(int)$signer['id'],'evidence_sha256',$evidence,['evidence_public_id'=>$evidenceId]);
            \Model::execute("UPDATE proma_sign_signers SET status='signed',signed_at=NOW() WHERE id=?",[(int)$signer['id']]);
            $remaining=\Model::fetch("SELECT COUNT(*) c FROM proma_sign_signers WHERE request_id=? AND status<>'signed'",[(int)$request['id']]);
            if((int)$remaining['c']===0)\Model::execute("UPDATE proma_sign_requests SET status='completed',completed_at=NOW() WHERE id=?",[(int)$request['id']]);
            if(class_exists('\\SystemOutbox'))\SystemOutbox::safeEnqueuePluginHook('proma.sign.completed',['request_id'=>(int)$request['id'],'signer_id'=>(int)$signer['id']],'proma_sign_request',(int)$request['id']);
            \Model::commit(); return ['ok'=>true,'evidence_public_id'=>$evidenceId,'evidence_hash'=>$hash];
        }catch(\Throwable $e){\Model::rollBack();throw $e;}
    }
    public function verifyPublic(string $publicId): array
    {
        $row=\Model::fetch('SELECT e.evidence_hash,e.previous_evidence_hash,e.evidence_json,e.document_hash,r.status,r.completed_at FROM proma_sign_evidence e JOIN proma_sign_requests r ON r.id=e.request_id WHERE e.public_id=?',[$publicId]);
        if(!$row)return ['valid'=>false,'reason'=>'not_found'];
        $valid=hash_equals($row['evidence_hash'],hash('sha256',($row['previous_evidence_hash']??'').$row['evidence_json']));
        return ['valid'=>$valid,'status'=>$row['status'],'completed_at'=>$row['completed_at'],'document_fingerprint'=>substr($row['document_hash'],0,12).'…'];
    }
    private function uuid(): string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
