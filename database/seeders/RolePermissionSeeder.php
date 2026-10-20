<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Panel
            'panel.access',

            // Autos
            'cars.view', 'cars.create', 'cars.update', 'cars.delete', 'cars.moderate',

            // Catálogos
            'catalogs.view', 'catalogs.manage',

            // Agencias
            'agencies.view', 'agencies.manage',

            // Leads y trámites
            'leads.view', 'leads.manage',
            'credit.view', 'credit.manage',

            // Planes y pagos
            'plans.view', 'plans.manage',
            'subscriptions.view', 'subscriptions.manage',
            'payments.view', 'payments.manage',

            // Contenido y configuración
            'ads.manage', 'financieras.manage',
            'settings.manage', 'integrations.manage',
            'reports.view', 'reports.manage',

            // Usuarios y roles
            'users.view', 'users.manage',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'superadmin' => $permissions,
            'manager' => array_values(array_diff($permissions, ['roles.manage'])),
            'viewer' => [
                'panel.access', 'cars.view', 'catalogs.view', 'agencies.view',
                'leads.view', 'credit.view', 'plans.view', 'subscriptions.view',
                'payments.view', 'reports.view', 'users.view',
            ],
            'seller' => ['panel.access', 'cars.view', 'cars.create', 'cars.update', 'cars.delete', 'credit.view'],
            'agency_owner' => [
                'panel.access', 'cars.view', 'cars.create', 'cars.update', 'cars.delete',
                'agencies.view', 'agencies.manage', 'leads.view', 'leads.manage',
                'credit.view', 'credit.manage', 'subscriptions.view', 'payments.view',
            ],
            'agency_admin' => [
                'panel.access', 'cars.view', 'cars.create', 'cars.update', 'cars.delete',
                'agencies.view', 'leads.view', 'leads.manage', 'credit.view', 'credit.manage',
            ],
            'agency_seller' => [
                'panel.access', 'cars.view', 'cars.create', 'cars.update',
                'leads.view', 'leads.manage', 'credit.view',
            ],
        ];

        foreach ($roles as $name => $rolePermissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
