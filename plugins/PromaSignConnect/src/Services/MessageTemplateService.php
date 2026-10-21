<?php
namespace Proma\Plugins\SignConnect\Services;

final class MessageTemplateService
{
    public function save(array $input, int $actorId): int
    {
        $eventKey = (string) ($input['event_key'] ?? '');
        $event = (new NotificationEventCatalog())->find($eventKey);
        if (!$event) throw new \InvalidArgumentException('رویداد قالب معتبر نیست.');
        $channel = (string) ($input['channel'] ?? '');
        if (!in_array($channel, ['in_app','ippanel','smsir','bale','telegram'], true)) {
            throw new \InvalidArgumentException('کانال قالب معتبر نیست.');
        }
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 4000) throw new \InvalidArgumentException('متن قالب الزامی و حداکثر ۴۰۰۰ نویسه است.');
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/', $body, $matches);
        foreach (array_unique($matches[1] ?? []) as $variable) {
            if (!in_array($variable, TemplateVariableRegistry::keys(), true)) {
                throw new \InvalidArgumentException('متغیر غیرمجاز در قالب: ' . $variable);
            }
        }
        $templateKey = $eventKey . '.' . $channel;
        \Model::begin();
        try {
            $existing = \Model::fetch(
                'SELECT * FROM proma_connect_templates WHERE template_key=? AND channel=? AND locale=? FOR UPDATE',
                [$templateKey,$channel,'fa']
            );
            if ($existing) {
                $id = (int) $existing['id'];
                \Model::execute(
                    'UPDATE proma_connect_templates SET body=?,is_active=?,updated_at=NOW() WHERE id=?',
                    [$body,!empty($input['is_active'])?1:0,$id]
                );
            } else {
                \Model::execute(
                    'INSERT INTO proma_connect_templates
                     (template_key,channel,locale,body,is_active,created_at,updated_at)
                     VALUES (?,?,"fa",?,?,NOW(),NOW())',
                    [$templateKey,$channel,$body,!empty($input['is_active'])?1:0]
                );
                $id = (int) \Model::lastInsertId();
            }
            $version = (int) (\Model::fetch(
                'SELECT COALESCE(MAX(version_no),0)+1 n FROM proma_connect_template_versions WHERE template_id=?',
                [$id]
            )['n'] ?? 1);
            \Model::execute(
                'INSERT INTO proma_connect_template_versions
                 (template_id,version_no,body,variables_json,change_reason,created_by,created_at)
                 VALUES (?,?,?,?,?,?,NOW())',
                [$id,$version,$body,json_encode(array_values(array_unique($matches[1]??[]))),mb_substr((string)($input['change_reason']??''),0,500),$actorId]
            );
            \Model::execute(
                "INSERT INTO proma_connect_settings_audit
                 (section_key,action_key,actor_id,after_json,request_id,created_at)
                 VALUES ('templates','template_version_created',?,?,?,NOW())",
                [$actorId,json_encode(['template_id'=>$id,'version'=>$version]),\ErrorHandler::requestId()]
            );
            \Model::commit();
            return $id;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }
}

