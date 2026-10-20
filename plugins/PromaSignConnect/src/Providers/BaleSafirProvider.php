<?php
namespace Proma\Plugins\SignConnect\Providers;
use Proma\Plugins\SignConnect\Services\BaleSafirPayloadBuilder;
final class BaleSafirProvider extends AbstractProvider
{
    public function getKey(): string { return 'bale'; }
    public function getCapabilities(): array { return ['text','otp','secure','template','button']; }
    public function send(array $message): array
    {
        $requestId=(string)($message['request_id']??$message['idempotency_key']??'');
        if(!preg_match('/^[0-9a-f-]{32,64}$/i',$requestId))throw new \InvalidArgumentException('bale_request_id_invalid');
        $payload=(new BaleSafirPayloadBuilder())->build($message,$requestId,$this->required('bot_id'));
        $started=microtime(true);
        $response=$this->http->request('POST',$this->config['base_url']??'https://safir.bale.ai/api/v3/send_message',[
            'api-access-key'=>$this->required('api_key'),'Content-Type'=>'application/json','Accept'=>'application/json','X-Request-ID'=>$requestId
        ],$payload,max(3,min(30,(int)($this->config['timeout']??10))));
        $body=$response['body'];$error=(string)($body['error']['name']??$body['error_name']??'');$id=$body['message_id']??$body['data']['message_id']??null;$ok=$response['status']>=200&&$response['status']<300&&$error==='';
        return ['ok'=>$ok,'external_id'=>(string)$id,'status'=>$response['status'],'duration_ms'=>(int)round((microtime(true)-$started)*1000),'error'=>$this->mapError($error,(int)$response['status']),'response'=>$this->redact($body)];
    }
    public function healthCheck(): array { return ['ok'=>trim((string)($this->config['api_key']??''))!==''&&preg_match('/^\d+$/',(string)($this->config['bot_id']??'')),'passive'=>true]; }
    private function mapError(string$name,int$status):array{$map=['InternalServerError'=>['retryable',true],'RateLimitExceeded'=>['retryable_after_delay',true],'InvalidInput'=>['invalid_payload',false],'InvalidPhone'=>['invalid_recipient',false],'NotBaleUser'=>['channel_unavailable',false],'PaymentRequired'=>['provider_credit_problem',false],'MaximumContactLimitReached'=>['provider_account_limit',false]];$statusMap=[401=>'provider_authentication_failed',403=>'provider_access_denied',404=>'provider_endpoint_not_found',408=>'retryable',429=>'retryable_after_delay'];if($status>=500)$statusMap[$status]='retryable';if(isset($map[$name]))return ['name'=>$name,'category'=>$map[$name][0],'retryable'=>$map[$name][1]];$category=$statusMap[$status]??'provider_error';return ['name'=>$name,'category'=>$category,'retryable'=>in_array($category,['retryable','retryable_after_delay'],true)];}
    private function redact(array$body):array{unset($body['api_access_key'],$body['api-access-key']);return$body;}
}
