<?php
namespace Proma\Plugins\SignConnect\Controllers;

use Proma\Plugins\SignConnect\Services\BaleAccountService;
use Proma\Plugins\SignConnect\Services\NotificationEventCatalog;
use Proma\Plugins\SignConnect\Services\ProviderEventMappingService;
use Proma\Plugins\SignConnect\Services\SettingsService;
use Proma\Plugins\SignConnect\Services\TemplateVariableRegistry;
use Proma\Plugins\SignConnect\Services\ModulePolicyService;
use Proma\Plugins\SignConnect\Services\SignerPolicyService;

final class DashboardController extends \Controller
{
    private const SECTIONS = [
        'overview','ippanel','smsir','bale','telegram',
        'otp','mfa','otp-security','signature','signers','hashes',
        'document-versions','evidence-retention','events','notifications','compose',
        'templates','variables','deliveries','health','retention','audit',
    ];

    public function index(): void { $this->section('overview'); }

    public function section($section = 'overview'): void
    {
        $section = (string) $section;
        if (!in_array($section, self::SECTIONS, true)) \ErrorHandler::abort(404);
        $data = [
            'title' => 'تنظیمات امضا و ارتباطات',
            'settings' => (new SettingsService())->all(false),
            'section' => $section,
            'counts' => ['requests'=>0,'signed'=>0,'queued'=>0,'dead'=>0],
            'baleAccounts' => [],
            'baleEdit' => null,
            'baleEventPermissions' => [],
            'rules' => [],
            'telegramLink' => null,
            'generatedTelegramLink' => $_SESSION['proma_telegram_link'] ?? null,
            'events' => [],
            'eventGroups' => [],
            'providerMappings' => [],
            'templateVariables' => TemplateVariableRegistry::definitions(),
            'variableProviderMappings' => [],
            'templates' => [],
            'deliveries' => [],
            'deliveryPage' => max(1, (int) ($_GET['page'] ?? 1)),
            'retentionPolicies' => [],
            'auditRows' => [],
            'healthRows' => [],
            'modulePolicy' => ['enabled'=>false,'version'=>1,'policy'=>[]],
            'signerPolicies' => [],
        ];
        unset($_SESSION['proma_telegram_link']);
        try {
            $data['counts'] = [
                'requests'=>(int)(\Model::fetch('SELECT COUNT(*) c FROM proma_sign_requests')['c']??0),
                'signed'=>(int)(\Model::fetch("SELECT COUNT(*) c FROM proma_sign_requests WHERE status='completed'")['c']??0),
                'queued'=>(int)(\Model::fetch("SELECT COUNT(*) c FROM proma_connect_deliveries WHERE status IN ('queued','retry','outbox_pending')")['c']??0),
                'dead'=>(int)(\Model::fetch("SELECT COUNT(*) c FROM proma_connect_deliveries WHERE status='dead'")['c']??0),
            ];
            $catalog = new NotificationEventCatalog();
            $data['events'] = $catalog->all((string) ($_GET['q'] ?? ''));
            $data['eventGroups'] = $catalog->grouped($data['events']);
            $data['rules'] = \Model::fetchAll('SELECT * FROM proma_connect_notification_rules ORDER BY event_key LIMIT 250');
            if (in_array($section, ['ippanel','smsir','bale','telegram'], true)) {
                $data['providerMappings'] = (new ProviderEventMappingService())->forProvider($section);
            }
            if ($section === 'bale') {
                $baleService=new BaleAccountService();$data['baleAccounts']=$baleService->all();
                $editId=(int)($_GET['edit']??0);
                if($editId>0){$data['baleEdit']=$baleService->find($editId);if($data['baleEdit'])$data['baleEventPermissions']=(new ProviderEventMappingService())->forProviderAccount('bale',$editId);}
            }
            if($section==='variables')$data['variableProviderMappings']=(new TemplateVariableRegistry())->providerMappings();
            if ($section === 'telegram') {
                $data['telegramLink'] = \Model::fetch(
                    "SELECT * FROM proma_connect_telegram_links WHERE user_id=? AND status='active' LIMIT 1",
                    [\Auth::id()]
                );
            }
            if ($section === 'templates') {
                $data['templates'] = \Model::fetchAll(
                    'SELECT t.*,COALESCE(MAX(v.version_no),0) current_version
                     FROM proma_connect_templates t
                     LEFT JOIN proma_connect_template_versions v ON v.template_id=t.id
                     GROUP BY t.id ORDER BY t.updated_at DESC,t.id DESC LIMIT 100'
                );
            }
            if ($section === 'deliveries') {
                $offset = ($data['deliveryPage'] - 1) * 25;
                $data['deliveries'] = \Model::fetchAll(
                    'SELECT id,provider_key,channel,recipient_hash,template_key,
                            provider_message_id,status,attempts,last_error_code,
                            created_at,updated_at,delivered_at
                     FROM proma_connect_deliveries ORDER BY id DESC LIMIT 25 OFFSET ' . $offset
                );
            }
            if ($section === 'retention') {
                $data['retentionPolicies'] = \Model::fetchAll(
                    'SELECT * FROM proma_connect_retention_policies ORDER BY policy_key'
                );
            }
            if ($section === 'audit') {
                $data['auditRows'] = \Model::fetchAll(
                    'SELECT section_key,action_key,actor_id,request_id,created_at
                     FROM proma_connect_settings_audit ORDER BY id DESC LIMIT 100'
                );
            }
            if (in_array($section,['mfa','otp-security','hashes','document-versions','evidence-retention'],true)) {
                $data['modulePolicy'] = (new ModulePolicyService())->get($section);
            }
            if ($section === 'signers') $data['signerPolicies'] = (new SignerPolicyService())->all();
            if ($section === 'health') $data['healthRows'] = $this->healthRows();
        } catch (\Throwable $e) {
            $data['loadError'] = 'بخشی از داده‌ها قابل بارگذاری نیست؛ migration افزونه و سلامت پایگاه داده را بررسی کنید.';
        }
        $this->render('plugin:proma-sign-connect/settings-center', $data);
    }

    public function save(): void
    {
        $this->onlyPost();
        $section = preg_replace('/[^a-z0-9_-]/', '', (string) ($_POST['_section'] ?? 'overview'));
        if (!in_array($section, self::SECTIONS, true)) \ErrorHandler::abort(404);
        $service = new SettingsService();
        try {
            $before = $service->all(false);
            $service->saveSection($section, $_POST, (int) \Auth::id());
            $after = $service->all(false);
            \Model::execute(
                "INSERT INTO proma_connect_settings_audit
                 (section_key,action_key,actor_id,before_json,after_json,request_id,created_at)
                 VALUES (?,'settings_saved',?,?,?,?,NOW())",
                [$section,\Auth::id(),json_encode($before),json_encode($after),\ErrorHandler::requestId()]
            );
            \set_flash('success', 'تنظیمات این بخش ذخیره شد.');
        } catch (\InvalidArgumentException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \set_flash('error', 'ذخیره تنظیمات انجام نشد. شناسه پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/' . $section);
    }

    private function healthRows(): array
    {
        $required = [
            'proma_connect_settings','proma_connect_event_catalog',
            'proma_connect_provider_event_mappings','proma_connect_deliveries',
            'proma_connect_otp_challenges','proma_sign_hash_registry',
        ];
        $rows = [];
        foreach ($required as $table) {
            $exists = \Model::fetch(
                'SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
                [$table]
            );
            $rows[] = ['label'=>$table,'ok'=>(int)($exists['c']??0)===1,'detail'=>'جدول پایگاه داده'];
        }
        $rows[] = ['label'=>'Core Outbox','ok'=>class_exists('SystemOutbox'),'detail'=>'صف مرکزی هسته'];
        $rows[] = ['label'=>'Secret Storage','ok'=>\Proma\Plugins\SignConnect\Services\SecretCipher::isReady(),'detail'=>'کلید اصلی خارج از دیتابیس و بسته افزونه'];
        return $rows;
    }
}
