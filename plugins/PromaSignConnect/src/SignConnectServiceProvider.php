<?php
namespace Proma\Plugins\SignConnect;
use Proma\Plugins\SignConnect\Services\SettingsService;
final class SignConnectServiceProvider implements \PluginServiceProviderInterface
{
    private static bool $autoloaded=false;
    public function register(\PluginManager $manager,array $manifest){$this->autoload($manifest['_root']);foreach($manifest['routes']??[]as$route)$manager->registerRoute($manifest['id'],$route['path'],$route['handler'],['method'=>$route['method']??'GET','permission'=>$route['permission']??'','auth'=>$route['auth']??true]);foreach([
        ['route'=>'plugin/sign-connect/settings/overview','label'=>'نمای کلی ارتباطات','icon'=>'activity','permission'=>'plugin.proma-sign-connect.proma_connect.view'],
        ['route'=>'plugin/sign-connect/settings/ippanel','label'=>'IPPanel','icon'=>'message-square','permission'=>'plugin.proma-sign-connect.proma_connect.providers.manage'],
        ['route'=>'plugin/sign-connect/settings/smsir','label'=>'SMS.ir','icon'=>'message-square','permission'=>'plugin.proma-sign-connect.proma_connect.providers.manage'],
        ['route'=>'plugin/sign-connect/settings/bale','label'=>'بازوهای بله','icon'=>'send','permission'=>'plugin.proma-sign-connect.proma_connect.providers.manage'],
        ['route'=>'plugin/sign-connect/settings/telegram','label'=>'ربات تلگرام','icon'=>'link','permission'=>'plugin.proma-sign-connect.proma_connect.providers.manage'],
        ['route'=>'plugin/sign-connect/settings/otp','label'=>'تنظیمات OTP','icon'=>'key','permission'=>'plugin.proma-sign-connect.proma_connect.otp.manage'],
        ['route'=>'plugin/sign-connect/settings/notifications','label'=>'قوانین اعلان','icon'=>'bell','permission'=>'plugin.proma-sign-connect.proma_connect.settings.manage'],
        ['route'=>'plugin/sign-connect/settings/compose','label'=>'ارسال پیام عمومی','icon'=>'send','permission'=>'plugin.proma-sign-connect.proma_connect.dispatch'],
        ['route'=>'plugin/sign-connect/settings/variables','label'=>'رجیستری متغیرها','icon'=>'code','permission'=>'plugin.proma-sign-connect.proma_connect.templates.manage'],
        ['route'=>'plugin/sign-connect/settings/signature','label'=>'تنظیمات امضا','icon'=>'edit-3','permission'=>'plugin.proma-sign-connect.proma_sign.view'],
    ]as$item){$item+=['group_label'=>'امضا و ارتباطات پروما','group_icon'=>'shield'];$manager->registerMenu($manifest['id'],$item);}}
    public function boot(\PluginManager $manager,array $manifest){
        foreach(['contract.created','contract.updated','contract.cancelled']as$event)$manager->registerHook($manifest['id'],$event,static function(array $payload)use($event){return $payload+['_sign_connect_observed'=>$event];},50);
        $manager->registerHook($manifest['id'],'proma.connect.dispatch',static function(array $payload){
            $deliveryId=(int)($payload['delivery_id']??0);
            if($deliveryId>0)(new \Proma\Plugins\SignConnect\Services\DispatchService())->processDelivery($deliveryId);
            return $payload;
        },50);
    }
    public function install(\PluginManager $manager,array $manifest){$this->autoload($manifest['_root']);\Proma\Plugins\SignConnect\Services\SecretCipher::ensureMasterKey();(new SettingsService())->save(SettingsService::defaults(),\Auth::id());}
    public function activate(\PluginManager $manager,array $manifest){$this->autoload($manifest['_root']);\Proma\Plugins\SignConnect\Services\SecretCipher::ensureMasterKey();if(!$this->healthCheck($manager,$manifest))throw new \RuntimeException('جداول افزونه آماده نیستند.');}
    public function deactivate(\PluginManager $manager,array $manifest){}
    public function update(\PluginManager $manager,array $manifest){$this->autoload($manifest['_root']);\Proma\Plugins\SignConnect\Services\SecretCipher::ensureMasterKey();}
    public function uninstall(\PluginManager $manager,array $manifest,$purge=false){if($purge)throw new \RuntimeException('حذف کامل شواهد امضا از چرخه عادی مجاز نیست؛ ابتدا پشتیبان، اسکن وابستگی و فرایند حقوقی انجام شود.');}
    public function healthCheck(\PluginManager $manager,array $manifest){$this->autoload($manifest['_root']);try{$tables=['proma_connect_settings','proma_connect_otp_challenges','proma_connect_deliveries','proma_sign_requests','proma_sign_evidence','proma_connect_provider_configs','proma_connect_notification_rules','proma_connect_telegram_link_tokens','proma_connect_telegram_links','proma_sign_hash_registry'];foreach($tables as$t){$row=\Model::fetch('SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);if((int)($row['c']??0)!==1)return false;}return true;}catch(\Throwable $e){return false;}}
    private function autoload(string $root):void{if(self::$autoloaded)return;$src=rtrim($root,'/\\').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR;spl_autoload_register(static function($class)use($src){$p='Proma\\Plugins\\SignConnect\\';if(strpos($class,$p)!==0)return;$f=$src.str_replace('\\',DIRECTORY_SEPARATOR,substr($class,strlen($p))).'.php';if(is_file($f))require_once$f;});self::$autoloaded=true;}
}
