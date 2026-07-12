<?php

class ContractPermission
{
    const KEYS = [
        'contract_settings.view',
        'contract_settings.manage',
        'contract_templates.view',
        'contract_templates.edit',
        'contract_templates.publish',
        'contract_templates.restore',
        'contract_print_settings.manage',
        'contract_documents.rebuild',
        'contract_documents.edit',
        'contract_documents.publish',
    ];

    public static function can($permission, $role = null)
    {
        $permission = trim((string) $permission);
        $role = $role ?: Auth::role();
        return in_array($permission, self::KEYS, true) && $role === 'admin';
    }

    public static function requirePermission($permission)
    {
        if (!self::can($permission)) {
            http_response_code(403);
            exit('شما اجازه دسترسی به این عملیات را ندارید.');
        }
    }
}
