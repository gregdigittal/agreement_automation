<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ShieldPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'system_admin' => ['*'],
            'legal' => [
                'view_any_contract', 'view_contract', 'create_contract', 'update_contract',
                'view_any_counterparty', 'view_counterparty', 'create_counterparty', 'update_counterparty',
                'view_any_wiki_contract', 'view_wiki_contract', 'create_wiki_contract', 'update_wiki_contract',
                'view_any_audit_log', 'view_audit_log',
                'view_any_override_request', 'view_override_request', 'update_override_request',
                'view_any_notification', 'view_notification',
                'page_Reports', 'page_EscalationsPage', 'page_KeyDatesPage', 'page_RemindersPage',
            ],
            'commercial' => [
                'view_any_contract', 'view_contract', 'create_contract',
                'view_any_counterparty', 'view_counterparty', 'create_counterparty',
                'view_any_merchant_agreement', 'view_merchant_agreement', 'create_merchant_agreement',
                'view_any_notification', 'view_notification',
                'page_KeyDatesPage', 'page_RemindersPage',
            ],
            'finance' => [
                'view_any_contract', 'view_contract',
                'page_Reports',
                'view_any_notification', 'view_notification',
            ],
            'operations' => [
                'view_any_contract', 'view_contract',
                'page_KeyDatesPage', 'page_RemindersPage',
                'view_any_notification', 'view_notification',
            ],
            'audit' => [
                'view_any_contract', 'view_contract',
                'view_any_audit_log', 'view_audit_log',
                'page_Reports',
                'view_any_notification', 'view_notification',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            if ($roleName === 'system_admin') {
                continue;
            }
            $role = Role::findByName($roleName);
            if (!$role) {
                continue;
            }
            $permModels = Permission::whereIn('name', $permissions)->get();
            $role->syncPermissions($permModels);
        }
    }
}
