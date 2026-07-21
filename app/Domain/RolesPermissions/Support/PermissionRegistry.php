<?php

namespace App\Domain\RolesPermissions\Support;

/**
 * Single source of truth for every permission in the system.
 * The RolePermissionSeeder syncs this registry into the database;
 * policies and UI read permission names from here.
 */
class PermissionRegistry
{
    /** Base CRUD-ish actions most modules share. */
    private const CRUD = ['view', 'create', 'update', 'delete'];

    /**
     * module => actions.
     *
     * @return array<string, list<string>>
     */
    public static function modules(): array
    {
        return [
            'branches' => [...self::CRUD, 'export'],
            'departments' => [...self::CRUD],
            'teams' => [...self::CRUD, 'assign'],
            'employees' => [...self::CRUD, 'assign', 'export'],
            'roles' => [...self::CRUD],
            'audit' => ['view', 'export'],
            'settings' => ['view', 'update'],
            'number-sequences' => ['view', 'update'],

            'sellers' => [...self::CRUD, 'view-kyc', 'view-bank-details', 'export'],
            'purchase-leads' => [...self::CRUD, 'assign', 'reassign', 'cancel', 'reopen', 'export'],
            'inspections' => [...self::CRUD, 'assign', 'approve', 'print', 'download', 'export'],
            'vehicle-verifications' => ['view', 'create', 'update', 'approve', 'reject'],
            'valuations' => ['view', 'create', 'update', 'approve', 'view-purchase-cost'],
            'purchase-approvals' => ['view', 'create', 'approve', 'reject'],
            'vehicle-purchases' => ['view', 'create', 'update', 'print', 'download', 'view-purchase-cost'],
            'seller-payments' => ['view', 'create', 'approve', 'reject', 'reverse-payment', 'view-bank-details', 'export'],
            'possessions' => ['view', 'create', 'update', 'print'],

            'vehicles' => [...self::CRUD, 'approve', 'print', 'export', 'view-purchase-cost', 'view-profit'],
            'refurbishment' => [...self::CRUD, 'approve', 'export'],
            'vendors' => [...self::CRUD, 'export'],

            // Vendor-sourced vehicle submissions (partner portal + staff review).
            // 'verify-documents' is a standalone right: an employee granted it can
            // verify owner-KYC documents without full review/approve authority.
            'vendor-submissions' => ['view', 'create', 'update', 'submit', 'review', 'verify-documents', 'approve', 'export'],
            'vendor-partners' => ['view', 'activate', 'export'],

            'customers' => [...self::CRUD, 'view-kyc', 'export'],
            'sales-leads' => [...self::CRUD, 'assign', 'reassign', 'cancel', 'reopen', 'export'],
            'telecalling' => ['view', 'create', 'update', 'export'],
            'visits' => ['view', 'create', 'update', 'cancel'],
            'test-drives' => ['view', 'create', 'update', 'cancel', 'print'],
            'bookings' => [...self::CRUD, 'approve', 'reject', 'cancel', 'print', 'download', 'export'],
            'finance' => [...self::CRUD, 'approve', 'export'],
            'payments' => ['view', 'create', 'approve', 'reject', 'reverse-payment', 'print', 'export'],
            'ledgers' => ['view', 'print', 'export'],
            'deliveries' => ['view', 'create', 'update', 'approve', 'print'],
            'rto-cases' => [...self::CRUD, 'assign', 'approve', 'print', 'export'],

            'documents' => ['view', 'create', 'download', 'print'],
            'templates' => [...self::CRUD, 'approve'],
            'approvals' => ['view', 'approve', 'reject'],
            'public-website' => ['view', 'update'],
            'reports' => ['view', 'export', 'access-reports'],
            'notifications' => ['view'],
        ];
    }

    /** Cross-cutting permissions not tied to one module. */
    public static function global(): array
    {
        return [
            'access-mobile',
        ];
    }

    /** @return list<string> flat list of every permission name */
    public static function all(): array
    {
        $names = [];

        foreach (self::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return [...$names, ...self::global()];
    }

    /** All permission names for the given modules (optionally filtered by actions). */
    public static function for(array $modules, ?array $only = null): array
    {
        $names = [];

        foreach ($modules as $module) {
            $actions = self::modules()[$module] ?? [];

            foreach ($actions as $action) {
                if ($only === null || in_array($action, $only, true)) {
                    $names[] = "{$module}.{$action}";
                }
            }
        }

        return $names;
    }
}
