<?php
namespace Proma\Plugins\SignConnect\Services;
final class OtpService
{
    public function create(string $mobile, string $purpose, ?int $userId = null): array
    {
        $mobile = $this->mobile($mobile); $purpose = preg_replace('/[^a-z0-9._-]/i','', $purpose);
        $settings = (new SettingsService())->all();
        try{$security=(new ModulePolicyService())->get('otp-security')['policy'];}catch(\Throwable$e){$security=[];}
        $mobileHash = hash('sha256', $mobile);
        $ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $recent = \Model::fetch('SELECT COUNT(*) c, MAX(created_at) last_at FROM proma_connect_otp_challenges WHERE mobile_hash=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$mobileHash]);
        $hourly=max(1,(int)($security['hourly_limit']??$settings['otp_hourly_limit']??5));
        $daily=max($hourly,(int)($security['daily_limit']??$settings['otp_daily_limit']??15));
        if ((int)($recent['c']??0) >= $hourly) throw new \RuntimeException('otp_rate_limited');
        $dailyCount=\Model::fetch('SELECT COUNT(*) c FROM proma_connect_otp_challenges WHERE mobile_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)',[$mobileHash]);
        if((int)($dailyCount['c']??0)>=$daily)throw new \RuntimeException('otp_rate_limited');
        $ipCount=\Model::fetch("SELECT COUNT(*) c FROM proma_connect_otp_security_events WHERE ip_hash=? AND event_type='requested' AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)",[$ipHash]);
        if((int)($ipCount['c']??0)>=max(5,(int)($security['ip_hourly_limit']??20)))throw new \RuntimeException('otp_rate_limited');
        $cooldown=max(30,(int)($security['resend_seconds']??$settings['otp_resend_seconds']));
        if (!empty($recent['last_at']) && time() - strtotime($recent['last_at']) < $cooldown) throw new \RuntimeException('otp_cooldown');
        $length = max(4,min(8,(int)($security['code_length']??$settings['otp_length'])));
        $code = str_pad((string)random_int(0,(10 ** $length)-1),$length,'0',STR_PAD_LEFT);
        $publicId = $this->uuid();
        \Model::begin();try{
            if(!empty($security['invalidate_previous']))\Model::execute("UPDATE proma_connect_otp_challenges SET status='superseded' WHERE mobile_hash=? AND purpose=? AND status='pending'",[$mobileHash,$purpose]);
            $active=\Model::fetch("SELECT COUNT(*) c FROM proma_connect_otp_challenges WHERE mobile_hash=? AND purpose=? AND status='pending' AND expires_at>NOW()",[$mobileHash,$purpose]);
            if((int)($active['c']??0)>=max(1,(int)($security['active_challenge_limit']??1)))throw new \RuntimeException('otp_active_challenge_limit');
            \Model::execute('INSERT INTO proma_connect_otp_challenges (public_id,user_id,mobile_hash,purpose,code_hash,status,attempts,max_attempts,expires_at,created_at) VALUES (?,?,?,?,?,\'pending\',0,?,DATE_ADD(NOW(), INTERVAL ? SECOND),NOW())', [$publicId,$userId,$mobileHash,$purpose,password_hash($code,PASSWORD_DEFAULT),max(3,(int)($security['max_attempts']??5)),max(60,(int)($security['ttl_seconds']??$settings['otp_ttl_seconds']))]);
            \Model::execute("INSERT INTO proma_connect_otp_security_events (challenge_public_id,user_id,mobile_hash,ip_hash,purpose,event_type,created_at) VALUES (?,?,?,?,?,'requested',NOW())",[$publicId,$userId,$mobileHash,$ipHash,$purpose]);
            \Model::commit();
        }catch(\Throwable$e){\Model::rollBack();throw$e;}
        return ['id'=>$publicId,'code'=>$code,'mobile'=>$mobile,'purpose'=>$purpose];
    }
    public function verify(string $publicId, string $code, string $purpose): bool
    {
        \Model::begin();
        try {
            $row = \Model::fetch('SELECT * FROM proma_connect_otp_challenges WHERE public_id=? FOR UPDATE', [$publicId]);
            if (!$row || $row['purpose'] !== $purpose || $row['status'] !== 'pending' || strtotime($row['expires_at']) < time() || (int)$row['attempts'] >= (int)$row['max_attempts']) { \Model::rollBack(); return false; }
            if (!password_verify($code, $row['code_hash'])) {
                \Model::execute("UPDATE proma_connect_otp_challenges SET attempts=attempts+1,status=IF(attempts+1>=max_attempts,'locked','pending') WHERE id=?", [(int)$row['id']]); \Model::commit(); return false;
            }
            \Model::execute("UPDATE proma_connect_otp_challenges SET status='consumed',consumed_at=NOW() WHERE id=? AND status='pending'", [(int)$row['id']]);
            \Model::commit(); return true;
        } catch (\Throwable $e) { \Model::rollBack(); throw $e; }
    }
    public function consumeForTransaction(string $publicId,string $code,string $purpose):bool
    {
        if(!\Model::db()->inTransaction())throw new \LogicException('otp_transaction_required');
        $row=\Model::fetch('SELECT * FROM proma_connect_otp_challenges WHERE public_id=? FOR UPDATE',[$publicId]);
        if(!$row||$row['purpose']!==$purpose||$row['status']!=='pending'||strtotime($row['expires_at'])<time()||(int)$row['attempts']>=(int)$row['max_attempts'])return false;
        if(!password_verify($code,$row['code_hash'])){\Model::execute("UPDATE proma_connect_otp_challenges SET attempts=attempts+1,status=IF(attempts+1>=max_attempts,'locked','pending') WHERE id=?",[(int)$row['id']]);return false;}
        return \Model::execute("UPDATE proma_connect_otp_challenges SET status='consumed',consumed_at=NOW() WHERE id=? AND status='pending'",[(int)$row['id']])===1;
    }
    private function mobile(string $value): string { $v=preg_replace('/\D+/','',$value); if(strlen($v)===10&&str_starts_with($v,'9'))$v='0'.$v; if(!preg_match('/^09\d{9}$/',$v))throw new \InvalidArgumentException('invalid_mobile'); return $v; }
    private function uuid(): string { $d=random_bytes(16); $d[6]=chr((ord($d[6])&15)|64); $d[8]=chr((ord($d[8])&63)|128); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
}
