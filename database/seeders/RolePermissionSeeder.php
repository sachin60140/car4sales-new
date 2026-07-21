<?php

namespace Database\Seeders;

use App\Domain\RolesPermissions\Models\Role;
use App\Domain\RolesPermissions\Models\RoleMeta;
use App\Domain\RolesPermissions\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Sync every permission from the registry.
        $names = PermissionRegistry::all();
        $existing = Permission::query()->pluck('name')->all();

        $now = now();
        $missing = array_values(array_diff($names, $existing));

        if ($missing !== []) {
            DB::table('permissions')->insert(array_map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ], $missing));
        }

        // 2. Roles: name => [data_scope, permissions]
        $viewEverything = PermissionRegistry::for(
            array_keys(PermissionRegistry::modules()),
            ['view', 'export', 'print', 'download'],
        );

        $roles = [
            'Super Admin' => ['all', []], // bypasses via Gate::before
            'Director' => ['all', [
                ...$viewEverything,
                ...PermissionRegistry::for(
                    ['inspections', 'valuations', 'purchase-approvals', 'seller-payments', 'vehicles',
                        'refurbishment', 'bookings', 'finance', 'payments', 'deliveries', 'rto-cases',
                        'templates', 'approvals'],
                    ['approve', 'reject'],
                ),
                'valuations.view-purchase-cost', 'vehicle-purchases.view-purchase-cost',
                'vehicles.view-purchase-cost', 'vehicles.view-profit',
                'sellers.view-kyc', 'sellers.view-bank-details', 'customers.view-kyc',
                'seller-payments.view-bank-details',
                'reports.view', 'reports.export', 'reports.access-reports',
                'audit.view', 'audit.export',
                'access-mobile',
            ]],
            'Owner' => ['all', ['@copy' => 'Director']],
            'Administrator' => ['all', [
                ...PermissionRegistry::for([
                    'branches', 'departments', 'teams', 'employees', 'roles',
                    'settings', 'number-sequences', 'templates', 'public-website', 'notifications', 'vendors',
                ]),
                'audit.view', 'audit.export',
                'reports.view', 'reports.access-reports',
            ]],
            'Branch Manager' => ['own_branch', [
                ...$viewEverything,
                ...PermissionRegistry::for(
                    ['inspections', 'valuations', 'purchase-approvals', 'seller-payments',
                        'refurbishment', 'bookings', 'deliveries', 'approvals'],
                    ['approve', 'reject'],
                ),
                'purchase-leads.assign', 'purchase-leads.reassign',
                'sales-leads.assign', 'sales-leads.reassign',
                'employees.view', 'teams.view',
                'valuations.view-purchase-cost', 'vehicles.view-purchase-cost', 'vehicles.view-profit',
                'reports.view', 'reports.export', 'reports.access-reports',
                'access-mobile',
            ]],
            'Purchase Manager' => ['own_branch', [
                ...PermissionRegistry::for(['purchase-leads', 'sellers', 'inspections',
                    'vehicle-verifications', 'valuations', 'possessions', 'vehicle-purchases']),
                'purchase-approvals.view', 'purchase-approvals.create', 'purchase-approvals.approve',
                'purchase-approvals.reject',
                'seller-payments.view', 'seller-payments.create', 'seller-payments.approve',
                'approvals.view', 'approvals.approve', 'approvals.reject',
                'vendor-submissions.view', 'vendor-submissions.review',
                'vendor-submissions.verify-documents', 'vendor-submissions.approve',
                'vendor-partners.view', 'vendor-partners.create', 'vendor-partners.update', 'vendor-partners.activate',
                'reports.view', 'reports.access-reports',
                'access-mobile',
            ]],
            'Purchase Executive' => ['assigned', [
                'purchase-leads.view', 'purchase-leads.create', 'purchase-leads.update',
                'sellers.view', 'sellers.create', 'sellers.update', 'sellers.view-kyc',
                'inspections.view', 'inspections.create',
                'vehicle-verifications.view', 'vehicle-verifications.create', 'vehicle-verifications.update',
                'valuations.view', 'valuations.create', 'valuations.update',
                'purchase-approvals.view', 'purchase-approvals.create',
                'vehicle-purchases.view', 'vehicle-purchases.create',
                'possessions.view', 'possessions.create', 'possessions.update',
                'vendor-submissions.view', 'vendor-submissions.review', 'vendor-submissions.verify-documents',
                'vendor-partners.view',
                'access-mobile',
            ]],
            // A focused role the admin can assign to any employee so they can verify
            // owner-KYC documents (view submissions + set per-document status) without
            // full review/approve authority.
            'Document Verifier' => ['own_branch', [
                'vendor-submissions.view', 'vendor-submissions.verify-documents',
                'access-mobile',
            ]],
            // External sourcing vendors — self-service partner portal only.
            'Vendor Partner' => ['assigned', [
                'vendor-submissions.view', 'vendor-submissions.create',
                'vendor-submissions.update', 'vendor-submissions.submit',
            ]],
            'Inspector' => ['assigned', [
                'inspections.view', 'inspections.create', 'inspections.update',
                'purchase-leads.view',
                'access-mobile',
            ]],
            'Inventory Manager' => ['own_branch', [
                ...PermissionRegistry::for(['vehicles', 'refurbishment']),
                'vendors.view', 'vendors.create', 'vendors.update',
                'reports.view', 'reports.access-reports',
                'access-mobile',
            ]],
            'Inventory Executive' => ['own_branch', [
                'vehicles.view', 'vehicles.update', 'vehicles.print',
                'refurbishment.view', 'refurbishment.create', 'refurbishment.update',
                'vendors.view',
                'access-mobile',
            ]],
            'Workshop Manager' => ['own_branch', [
                ...PermissionRegistry::for(['refurbishment']),
                ...PermissionRegistry::for(['vendors']),
                'vehicles.view', 'vehicles.update',
                'access-mobile',
            ]],
            'Telecalling Manager' => ['own_branch', [
                'sales-leads.view', 'sales-leads.assign', 'sales-leads.reassign', 'sales-leads.export',
                ...PermissionRegistry::for(['telecalling']),
                'customers.view', 'visits.view', 'test-drives.view',
                'reports.view', 'reports.export', 'reports.access-reports',
            ]],
            'Team Leader' => ['own_team', [
                'sales-leads.view', 'sales-leads.update', 'sales-leads.assign', 'sales-leads.reassign',
                'telecalling.view', 'telecalling.create', 'telecalling.update',
                'visits.view', 'visits.create', 'visits.update',
                'test-drives.view', 'customers.view',
                'reports.view',
            ]],
            'Telecaller' => ['assigned', [
                'sales-leads.view', 'sales-leads.update',
                'telecalling.view', 'telecalling.create', 'telecalling.update',
                'visits.view', 'visits.create', 'visits.update',
                'test-drives.view', 'test-drives.create',
                'customers.view', 'customers.create', 'customers.update',
                'access-mobile',
            ]],
            'Sales Manager' => ['own_branch', [
                ...PermissionRegistry::for(['sales-leads', 'visits', 'test-drives', 'bookings', 'customers']),
                'vehicles.view', 'vehicles.update',
                'deliveries.view', 'deliveries.approve',
                'approvals.view', 'approvals.approve', 'approvals.reject',
                'reports.view', 'reports.export', 'reports.access-reports',
            ]],
            'Sales Executive' => ['assigned', [
                'sales-leads.view', 'sales-leads.update',
                'visits.view', 'visits.create', 'visits.update',
                'test-drives.view', 'test-drives.create', 'test-drives.update',
                'bookings.view', 'bookings.create',
                'customers.view', 'customers.create', 'customers.update', 'customers.view-kyc',
                'vehicles.view',
                'access-mobile',
            ]],
            'Finance Manager' => ['own_branch', [
                ...PermissionRegistry::for(['finance']),
                'customers.view', 'customers.view-kyc', 'bookings.view',
                'reports.view', 'reports.access-reports',
            ]],
            'Finance Executive' => ['assigned', [
                'finance.view', 'finance.create', 'finance.update',
                'customers.view', 'customers.view-kyc', 'bookings.view',
            ]],
            'Accounts Manager' => ['own_branch', [
                ...PermissionRegistry::for(['payments', 'ledgers']),
                'seller-payments.view', 'seller-payments.approve', 'seller-payments.reverse-payment',
                'seller-payments.view-bank-details',
                'sellers.view-bank-details',
                'bookings.view', 'reports.view', 'reports.export', 'reports.access-reports',
            ]],
            'Cashier' => ['own_branch', [
                'payments.view', 'payments.create', 'payments.print',
                'ledgers.view', 'ledgers.print',
            ]],
            'Delivery Manager' => ['own_branch', [
                ...PermissionRegistry::for(['deliveries']),
                'bookings.view', 'reports.view',
            ]],
            'Delivery Executive' => ['assigned', [
                'deliveries.view', 'deliveries.create', 'deliveries.update',
                'bookings.view',
                'access-mobile',
            ]],
            'RTO Manager' => ['own_branch', [
                ...PermissionRegistry::for(['rto-cases']),
                'documents.view', 'documents.create', 'documents.download',
                'reports.view', 'reports.access-reports',
            ]],
            'RTO Executive' => ['assigned', [
                'rto-cases.view', 'rto-cases.update',
                'documents.view', 'documents.create',
                'access-mobile',
            ]],
            'Legal User' => ['all', [
                'sellers.view', 'sellers.view-kyc',
                'customers.view', 'customers.view-kyc',
                'documents.view', 'documents.download',
                'audit.view',
            ]],
            'Auditor' => ['read_only', [
                ...$viewEverything,
                'valuations.view-purchase-cost', 'vehicle-purchases.view-purchase-cost',
                'vehicles.view-purchase-cost', 'vehicles.view-profit',
                'sellers.view-kyc', 'sellers.view-bank-details', 'customers.view-kyc',
                'seller-payments.view-bank-details',
                'audit.view', 'audit.export',
                'reports.view', 'reports.export', 'reports.access-reports',
            ]],
        ];

        $granted = [];

        foreach ($roles as $name => [$scope, $permissions]) {
            if (($permissions['@copy'] ?? null) !== null) {
                $permissions = $granted[$permissions['@copy']];
            }

            $permissions = array_values(array_unique($permissions));
            $granted[$name] = $permissions;

            /** @var Role $role */
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            RoleMeta::query()->updateOrCreate(
                ['role_id' => $role->id],
                ['data_scope' => $scope, 'is_system' => true],
            );
        }

        // 3. Default approval limits for the purchase chain.
        foreach ([
            ['Purchase Manager', 'purchase-approvals', 300000.00, true],
            ['Branch Manager', 'purchase-approvals', 800000.00, true],
            ['Director', 'purchase-approvals', null, false],
            ['Sales Manager', 'discounts', 25000.00, true],
            ['Branch Manager', 'discounts', 100000.00, false],
            ['Director', 'discounts', null, false],
            ['Accounts Manager', 'refunds', 50000.00, true],
            ['Branch Manager', 'refunds', 200000.00, true],
            ['Director', 'refunds', null, false],
        ] as [$roleName, $module, $maxAmount, $escalates]) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role !== null) {
                DB::table('approval_limits')->updateOrInsert(
                    ['role_id' => $role->id, 'module' => $module],
                    ['max_amount' => $maxAmount, 'requires_escalation' => $escalates,
                        'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
