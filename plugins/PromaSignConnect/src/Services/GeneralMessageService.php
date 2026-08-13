<?php
namespace Proma\Plugins\SignConnect\Services;

final class GeneralMessageService
{
    private const CHANNELS = ['in_app','ippanel','smsir','bale','telegram'];

    public function send(array $input, int $actorId): array
    {
        $title = trim(strip_tags((string) ($input['title'] ?? '')));
        $text = trim((string) ($input['message'] ?? ''));
        if ($title === '' || mb_strlen($title) > 190) throw new \InvalidArgumentException('عنوان پیام الزامی و حداکثر ۱۹۰ نویسه است.');
        if ($text === '' || mb_strlen($text) > 2000) throw new \InvalidArgumentException('متن پیام الزامی و حداکثر ۲۰۰۰ نویسه است.');

        $channels = array_values(array_intersect(self::CHANNELS, array_map('strval', (array) ($input['channels'] ?? []))));
        if (!$channels) throw new \InvalidArgumentException('حداقل یک کانال ارسال را انتخاب کنید.');
        $registry = new ProviderRegistry();
        foreach ($channels as $channel) {
            if (!$registry->supports($channel, 'custom_message')) {
                throw new \InvalidArgumentException('قابلیت «پیام عمومی» برای کانال ' . $this->label($channel) . ' فعال نشده است.');
            }
        }

        $mobiles = $this->mobiles((string) ($input['mobiles'] ?? ''));
        $userIds = $this->userIds((string) ($input['user_ids'] ?? ''));
        if (array_intersect($channels, ['ippanel','smsir','bale']) && !$mobiles) throw new \InvalidArgumentException('برای کانال پیامکی یا بله، حداقل یک شماره موبایل وارد کنید.');
        if (array_intersect($channels, ['in_app','telegram']) && !$userIds) throw new \InvalidArgumentException('برای اعلان داخل سامانه یا تلگرام، حداقل یک شناسه کاربر وارد کنید.');
        if (count($mobiles) + count($userIds) > 100) throw new \InvalidArgumentException('در هر نوبت حداکثر ۱۰۰ مخاطب مجاز است.');

        $dispatch = new DispatchService();
        $request = \ErrorHandler::requestId();
        $campaignPublicId = $this->uuid();
        $queued = 0;
        \Model::begin();
        try {
            \Model::execute("INSERT INTO proma_connect_broadcast_campaigns (public_id,title,message_body,channels_json,audience_json,status,total_recipients,created_by,created_at,started_at) VALUES (?,?,?,?,?,'queueing',?,?,NOW(),NOW())",[$campaignPublicId,$title,$text,json_encode($channels),json_encode(['mobile_count'=>count($mobiles),'user_count'=>count($userIds)]),count($mobiles)+count($userIds),$actorId]);
            $campaignId=(int)\Model::lastInsertId();
            foreach ($channels as $channel) {
                if (in_array($channel, ['ippanel','smsir','bale'], true)) {
                    foreach ($mobiles as $mobile) {
                        $deliveryId=$dispatch->enqueue([
                            'provider'=>$channel,'channel'=>$channel,'to'=>$mobile,'title'=>$title,'text'=>$text,
                            'capability'=>'custom_message','template_key'=>'custom_message',
                            'request_id'=>$request,'idempotency_key'=>hash('sha256',$campaignPublicId.'|'.$channel.'|'.$mobile),
                        ]);
                        $this->recordCampaignDispatch($campaignId,$mobile,$channel,$deliveryId);
                        $queued++;
                    }
                } elseif ($channel === 'in_app') {
                    foreach ($userIds as $userId) {
                        $deliveryId=$dispatch->enqueue([
                            'provider'=>'in_app','channel'=>'in_app','to'=>(string)$userId,'user_id'=>$userId,
                            'title'=>$title,'text'=>$text,'type'=>'general','capability'=>'custom_message',
                            'template_key'=>'custom_message','request_id'=>$request,
                            'idempotency_key'=>hash('sha256',$campaignPublicId.'|in_app|'.$userId),
                        ]);
                        $this->recordCampaignDispatch($campaignId,(string)$userId,'in_app',$deliveryId);
                        $queued++;
                    }
                } else {
                    foreach ($userIds as $userId) {
                        $link = \Model::fetch("SELECT chat_id FROM proma_connect_telegram_links WHERE user_id=? AND bot_id=? AND status='active' AND notifications_enabled=1 ORDER BY id DESC LIMIT 1", [$userId,(string)((new SettingsService())->all(false)['telegram_bot_id']??'')]);
                        if (!$link) throw new \InvalidArgumentException('حساب تلگرام کاربر ' . $userId . ' متصل نیست.');
                        $deliveryId=$dispatch->enqueue([
                            'provider'=>'telegram','channel'=>'telegram','chat_id'=>(string)$link['chat_id'],'to'=>(string)$link['chat_id'],
                            'title'=>$title,'text'=>htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),'capability'=>'custom_message','template_key'=>'custom_message',
                            'request_id'=>$request,'idempotency_key'=>hash('sha256',$campaignPublicId.'|telegram|'.$userId),
                        ]);
                        $this->recordCampaignDispatch($campaignId,(string)$userId,'telegram',$deliveryId);
                        $queued++;
                    }
                }
            }
            \Model::execute("UPDATE proma_connect_broadcast_campaigns SET status='queued',queued_count=?,completed_at=IF(?=0,NOW(),NULL) WHERE id=?",[$queued,$queued,$campaignId]);
            \Model::execute(
                "INSERT INTO proma_connect_settings_audit (section_key,action_key,actor_id,before_json,after_json,request_id,created_at) VALUES ('compose','messages_queued',?,NULL,?,?,NOW())",
                [$actorId,json_encode(['channels'=>$channels,'queued'=>$queued,'mobile_count'=>count($mobiles),'user_count'=>count($userIds)],JSON_UNESCAPED_UNICODE),$request]
            );
            \Model::commit();
        } catch (\Throwable $e) {
            if (\Model::db()->inTransaction()) \Model::rollBack();
            throw $e;
        }
        return ['queued'=>$queued,'channels'=>$channels,'campaign'=>$campaignPublicId];
    }

    private function mobiles(string $value): array
    {
        $result=[];
        foreach (preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $mobile) $result[] = IranianPhoneNumberNormalizer::normalize($mobile);
        return array_values(array_unique($result));
    }
    private function userIds(string $value): array
    {
        $result=[];
        foreach (preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
            if (!ctype_digit($id) || (int)$id < 1) throw new \InvalidArgumentException('شناسه کاربر معتبر نیست.');
            $result[]=(int)$id;
        }
        return array_values(array_unique($result));
    }
    private function label(string $channel): string
    {
        return ['in_app'=>'داخل سامانه','ippanel'=>'IPPanel','smsir'=>'SMS.ir','bale'=>'بله','telegram'=>'تلگرام'][$channel] ?? $channel;
    }
    private function recordCampaignDispatch(int $campaignId,string $recipient,string $channel,int $deliveryId):void
    {
        \Model::execute("INSERT IGNORE INTO proma_connect_broadcast_dispatches (campaign_id,recipient_key,channel,delivery_id,status,created_at) VALUES (?,?,?,?,'queued',NOW())",[$campaignId,hash('sha256',$recipient),$channel,$deliveryId]);
    }
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
