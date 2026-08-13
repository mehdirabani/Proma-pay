<?php
namespace Proma\Plugins\SignConnect\Services;
final class NotificationRuleResolver
{
    public function resolve(string$event,array$recipient):array
    {
        $rule=\Model::fetch('SELECT * FROM proma_connect_notification_rules WHERE event_key=? AND is_enabled=1',[$event]);if(!$rule)return['eligible'=>false,'reason'=>'event_disabled','attempts'=>[]];
        $channels=json_decode($rule['channels_json'],true)?:[];$attempts=[];foreach($channels as$channel=>$mode){if($mode==='disabled')continue;if($channel==='telegram'){$link=\Model::fetch("SELECT chat_id FROM proma_connect_telegram_links WHERE user_id=? AND status='active' AND notifications_enabled=1 LIMIT 1",[(int)$recipient['user_id']]);if(!$link)continue;$attempts[]=['channel'=>'telegram','mode'=>$mode,'chat_id'=>$link['chat_id']];}elseif($channel==='bale'){$account=\Model::fetch("SELECT id FROM proma_connect_provider_configs WHERE provider_key='bale' AND is_active=1 AND status='verified' ORDER BY is_default DESC,id LIMIT 1");if($account)$attempts[]=['channel'=>'bale','mode'=>$mode,'account_id'=>(int)$account['id']];}else$attempts[]=['channel'=>$channel,'mode'=>$mode];}
        return['eligible'=>(bool)$attempts,'event'=>$event,'sensitivity'=>$rule['sensitivity'],'attempts'=>$attempts,'dedupe_seconds'=>(int)$rule['dedupe_seconds']];
    }
}
