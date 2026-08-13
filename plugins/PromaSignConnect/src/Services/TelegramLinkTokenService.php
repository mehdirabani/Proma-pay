<?php
namespace Proma\Plugins\SignConnect\Services;
final class TelegramLinkTokenService
{
    public function create(int$userId,string$userType,string$botId,string$username,int$ttl=600):array
    {
        if(!preg_match('/^\d+$/',$botId)||!preg_match('/^[A-Za-z0-9_]{5,32}$/',$username))throw new \RuntimeException('هویت ربات تأیید نشده است.');
        $raw=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$hash=$this->hash($raw);$request=$this->uuid();
        \Model::execute("UPDATE proma_connect_telegram_link_tokens SET status='revoked',revoked_at=NOW() WHERE user_id=? AND status='active'",[$userId]);
        \Model::execute("INSERT INTO proma_connect_telegram_link_tokens (token_hash,token_prefix,user_id,user_type,intended_bot_id,status,request_id,created_by,created_at,expires_at) VALUES (?,?,?,?,?,'active',?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND))",[$hash,substr($raw,0,8),$userId,$userType,$botId,$request,\Auth::id(),max(60,min(3600,$ttl))]);
        return ['url'=>'https://t.me/'.$username.'?start='.$raw,'expires_in'=>$ttl,'token_prefix'=>substr($raw,0,8)];
    }
    public function consume(string$raw,array$telegram,string$botId):array
    {
        if(strlen($raw)>64||!preg_match('/^[A-Za-z0-9_-]{20,64}$/',$raw))throw new \InvalidArgumentException('invalid_link_token');
        \Model::begin();try{$token=\Model::fetch("SELECT t.*,u.status user_status FROM proma_connect_telegram_link_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? FOR UPDATE",[$this->hash($raw)]);if(!$token||$token['status']!=='active'||strtotime($token['expires_at'])<time()||$token['user_status']!=='active'||!hash_equals((string)$token['intended_bot_id'],$botId))throw new \RuntimeException('expired_or_invalid_link');
            $conflict=\Model::fetch("SELECT user_id FROM proma_connect_telegram_links WHERE bot_id=? AND telegram_user_id=? AND status='active' FOR UPDATE",[$botId,(string)$telegram['user_id']]);if($conflict&&(int)$conflict['user_id']!==(int)$token['user_id'])throw new \RuntimeException('telegram_identity_conflict');
            \Model::execute("UPDATE proma_connect_telegram_links SET status='disconnected',disconnected_at=NOW(),disconnect_reason='replaced_by_verified_link' WHERE user_id=? AND bot_id=? AND status='active'",[(int)$token['user_id'],$botId]);
            \Model::execute("INSERT INTO proma_connect_telegram_links (user_id,user_type,bot_id,telegram_user_id,chat_id,username,display_name,language_code,status,linked_at,verified_at,last_interaction_at,created_at) VALUES (?,?,?,?,?,?,?,?,'active',NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),user_type=VALUES(user_type),chat_id=VALUES(chat_id),username=VALUES(username),display_name=VALUES(display_name),language_code=VALUES(language_code),status='active',verified_at=NOW(),last_interaction_at=NOW(),disconnected_at=NULL",[(int)$token['user_id'],$token['user_type'],$botId,(string)$telegram['user_id'],(string)$telegram['chat_id'],$telegram['username']??null,$telegram['display_name']??null,$telegram['language_code']??null]);
            \Model::execute("UPDATE proma_connect_telegram_link_tokens SET status='used',used_at=NOW() WHERE id=?",[(int)$token['id']]);\Model::commit();return ['user_id'=>(int)$token['user_id'],'already_linked'=>(bool)$conflict];
        }catch(\Throwable$e){\Model::rollBack();throw$e;}
    }
    private function hash(string$raw):string{$key=(string)(getenv('PROMA_TELEGRAM_LINK_KEY')?:getenv('PROMA_APP_KEY'));if(strlen($key)<16)throw new \RuntimeException('telegram_link_key_missing');return hash_hmac('sha256',$raw,$key);}
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
