<?php
namespace Proma\Plugins\SignConnect\Services;
final class SignatureHashRegistry
{
    public function record(int$requestId,?int$signerId,string$type,$value,array$metadata=[]):array
    {
        try{$policy=(new ModulePolicyService())->get('hashes')['policy'];}catch(\Throwable$e){$policy=[];}$algorithm=$policy['algorithm']??'sha256';$canonicalVersion=$policy['canonicalization']??'json-c14n-v1';$keyVersion=(int)($policy['active_key_version']??1);
        $canonical=$this->canonical($value);$digest=hash($algorithm,$canonical);$previous=\Model::fetch('SELECT authenticity_hmac FROM proma_sign_hash_registry WHERE request_id=? ORDER BY id DESC LIMIT 1',[$requestId]);$hmac=hash_hmac('sha256',($previous['authenticity_hmac']??'').$type.$digest,$this->key($keyVersion));$public=$this->uuid();$metadata+=['hmac_key_version'=>$keyVersion];
        \Model::execute("INSERT INTO proma_sign_hash_registry (public_id,request_id,signer_id,hash_type,algorithm,algorithm_version,canonicalization_version,digest,authenticity_hmac,previous_event_hash,metadata_json,created_at) VALUES (?,?,?,?,?,'1',?,?,?,?,?,NOW())",[$public,$requestId,$signerId,$type,$algorithm,$canonicalVersion,$digest,$hmac,$previous['authenticity_hmac']??null,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        return ['public_id'=>$public,'digest'=>$digest,'hmac'=>$hmac];
    }
    public function verifyRequest(int$requestId):array{$rows=\Model::fetchAll('SELECT * FROM proma_sign_hash_registry WHERE request_id=? ORDER BY id',[$requestId]);$previous=null;foreach($rows as$r){$meta=json_decode((string)($r['metadata_json']??''),true)?:[];$expected=hash_hmac('sha256',($previous??'').$r['hash_type'].$r['digest'],$this->key((int)($meta['hmac_key_version']??1)));if(!hash_equals($r['authenticity_hmac'],$expected)||($r['previous_event_hash']??null)!==$previous)return['valid'=>false,'failed_public_id'=>$r['public_id']];$previous=$r['authenticity_hmac'];}return['valid'=>true,'entries'=>count($rows),'last_hash'=>$previous];}
    private function canonical($v):string{if(is_string($v))return$v;$sort=function(&$a)use(&$sort){if(!is_array($a))return;if(array_keys($a)!==range(0,count($a)-1))ksort($a,SORT_STRING);foreach($a as&$x)$sort($x);};$sort($v);return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);}
    private function key(int$version=1):string{$k=(string)getenv('PROMA_SIGNATURE_HMAC_KEY_V'.$version);if($k===''&&$version===1)$k=(string)getenv('PROMA_SIGNATURE_HMAC_KEY');if(strlen($k)<32)throw new \RuntimeException('Signature HMAC key version is not configured outside the database.');return$k;}
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
