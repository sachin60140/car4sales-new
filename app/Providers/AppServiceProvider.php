<?php

namespace App\Providers;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Policies\ApprovalRequestPolicy;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Policies\BookingPolicy;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Policies\BranchPolicy;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Deliveries\Policies\DeliveryPolicy;
use App\Domain\Departments\Models\Department;
use App\Domain\Departments\Policies\DepartmentPolicy;
use App\Domain\Employees\Policies\EmployeePolicy;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\Inspections\Policies\VehicleInspectionPolicy;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Policies\VehiclePolicy;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Policies\CustomerPolicy;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Finance\Policies\FinanceApplicationPolicy;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\PurchaseLeads\Policies\PurchaseLeadPolicy;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\SalesLeads\Policies\SalesLeadPolicy;
use App\Domain\Refurbishment\Models\WorkshopJob;
use App\Domain\Refurbishment\Policies\WorkshopJobPolicy;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\RolesPermissions\Policies\RolePolicy;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\RTO\Policies\RtoCasePolicy;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Policies\TeamPolicy;
use App\Domain\Vendors\Models\Vendor;
use App\Domain\Vendors\Policies\VendorPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(User::class, EmployeePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(PurchaseLead::class, PurchaseLeadPolicy::class);
        Gate::policy(VehicleInspection::class, VehicleInspectionPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(WorkshopJob::class, WorkshopJobPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(SalesLead::class, SalesLeadPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(FinanceApplication::class, FinanceApplicationPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(RtoCase::class, RtoCasePolicy::class);

        // Super Admin bypasses all permission checks.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        Password::defaults(function () {
            $config = config('car4sales.password');

            $rule = Password::min($config['min_length'] ?? 8);

            if ($config['require_mixed_case'] ?? false) {
                $rule = $rule->mixedCase();
            }

            if ($config['require_numbers'] ?? false) {
                $rule = $rule->numbers();
            }

            if ($config['require_symbols'] ?? false) {
                $rule = $rule->symbols();
            }

            return $rule;
        });
    }
}
