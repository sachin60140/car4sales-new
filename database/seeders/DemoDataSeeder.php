<?php

namespace Database\Seeders;

use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\Inspections\Models\InspectionChecklistItem;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\Sellers\Models\Seller;
use App\Domain\Vendors\Models\Vendor;
use App\Domain\VehicleVerification\Services\VerificationChecklist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Development-only demo data. Populates branches, employees, sellers, purchase
 * leads, inspections and stock so the dashboard, charts and lists have something
 * to show.
 *
 *   php artisan db:seed --class=DemoDataSeeder      # (re)seed demo data
 *   php artisan db:seed --class=DemoDataClearSeeder # remove demo data
 *
 * Everything it creates is tagged with a DEMO marker (see the *_MARK constants),
 * so it never collides with real records and never advances the production
 * number sequences. Re-running first clears the previous demo set, so it is
 * safe to run repeatedly.
 */
class DemoDataSeeder extends Seeder
{
    public const USER_DOMAIN = '@demo.car4sales.test';
    public const LEAD_PREFIX = 'PL-DEMO-';
    public const STOCK_PREFIX = 'STK-DEMO-';
    public const INSPECTION_PREFIX = 'INS-DEMO-';
    public const SELLER_PREFIX = 'SELL-DEMO-';
    public const BRANCH_PREFIX = 'BR-DEMO-';
    public const EMP_PREFIX = 'EMP-DEMO-';
    public const VENDOR_PREFIX = 'VEN-DEMO-';
    public const CUSTOMER_PREFIX = 'CUST-DEMO-';
    public const SALES_LEAD_PREFIX = 'SL-DEMO-';

    private const MAKES = [
        'Maruti' => ['Swift', 'Baleno', 'Dzire', 'Brezza'],
        'Hyundai' => ['i20', 'Creta', 'Venue', 'Verna'],
        'Honda' => ['City', 'Amaze', 'Jazz'],
        'Tata' => ['Nexon', 'Punch', 'Tiago', 'Harrier'],
        'Kia' => ['Seltos', 'Sonet', 'Carens'],
    ];

    private const CITIES = ['Lucknow', 'Kanpur', 'Varanasi', 'Prayagraj', 'Agra', 'Gorakhpur'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder is disabled in production. Aborting.');

            return;
        }

        // Ensure the foundational data exists before layering demo data on top.
        if (! Role::query()->where('name', 'Super Admin')->exists()) {
            $this->call([DepartmentSeeder::class, BranchSeeder::class, RolePermissionSeeder::class, AdminUserSeeder::class]);
        }
        if (! InspectionChecklistItem::query()->exists()) {
            $this->call(InspectionChecklistSeeder::class);
        }

        self::clear();

        // Suppress event-driven notifications while generating demo records — the
        // only demo notifications are the curated set from seedNotifications().
        \App\Domain\Notifications\Services\NotificationService::mute();

        try {
            $branches = $this->seedBranches();
            $employees = $this->seedEmployees($branches);
            $employeeCount = array_sum(array_map('count', $employees));
            $sellers = $this->seedSellers();
            $vendors = $this->seedVendors($branches);
            $leads = $this->seedLeads($branches, $employees, $sellers);
            $inspectionCount = $this->seedInspections($leads, $employees);
            $vehicleCount = $this->seedVehicles($branches, $vendors);
            $salesLeadCount = $this->seedSalesLeads($branches, $employees);
            $bookingCount = $this->seedBookings($employees);
            [$deliveryCount, $rtoCount] = $this->seedDeliveries($employees);
            [$vendorPartnerCount, $vendorSubmissionCount] = $this->seedVendorPartners($branches);
        } finally {
            \App\Domain\Notifications\Services\NotificationService::unmute();
        }

        $notificationCount = $this->seedNotifications();

        $this->command?->info(sprintf(
            'Demo data seeded: %d branches, %d employees, %d sellers, %d vendors, %d purchase leads, %d inspections, %d vehicles, %d sales leads, %d bookings, %d deliveries, %d RTO cases, %d vendor partners, %d vendor submissions, %d notifications.',
            count($branches),
            $employeeCount,
            count($sellers),
            count($vendors),
            count($leads),
            $inspectionCount,
            $vehicleCount,
            $salesLeadCount,
            $bookingCount,
            $deliveryCount,
            $rtoCount,
            $vendorPartnerCount,
            $vendorSubmissionCount,
            $notificationCount,
        ));
    }

    /**
     * Remove every demo record. FK cascades handle child rows; leads are removed
     * before their branches so nothing is left dangling.
     */
    public static function clear(): void
    {
        // Purchase leads created when a demo vendor submission was approved. Read
        // them before the vendor users (and their cascading submissions) are gone.
        $vendorLeadIds = \App\Domain\VendorSubmissions\Models\VendorSubmission::withTrashed()
            ->whereHas('vendor', fn ($q) => $q->where('email', 'like', '%'.self::USER_DOMAIN))
            ->whereNotNull('purchase_lead_id')
            ->pluck('purchase_lead_id')
            ->filter();
        if ($vendorLeadIds->isNotEmpty()) {
            PurchaseLead::withTrashed()->whereIn('id', $vendorLeadIds)->get()->each(fn (PurchaseLead $l) => $l->forceDelete());
        }

        // Phase 9: demo notifications are tagged in their data payload. Demo-user
        // notifications also cascade when those users are deleted below, but the
        // admin's demo notifications must be cleared explicitly.
        \App\Domain\Notifications\Models\Notification::query()
            ->where('data->demo', true)->delete();

        // Phase 8: RTO cases + deliveries hold restrict FKs to demo vehicles, so
        // they must be removed before the vehicles (and bookings) below.
        \App\Domain\RTO\Models\RtoCase::withTrashed()
            ->whereHas('vehicle', fn ($q) => $q->where('stock_number', 'like', self::STOCK_PREFIX.'%'))
            ->get()->each(fn ($r) => $r->forceDelete());
        \App\Domain\Deliveries\Models\Delivery::withTrashed()
            ->whereHas('vehicle', fn ($q) => $q->where('stock_number', 'like', self::STOCK_PREFIX.'%'))
            ->get()->each(fn ($d) => $d->forceDelete());

        // Bookings/visits/test-drives tied to demo customers or vehicles must go
        // first — bookings hold restrict FKs to customers and vehicles.
        \App\Domain\Bookings\Models\Booking::withTrashed()
            ->whereHas('customer', fn ($q) => $q->where('customer_code', 'like', self::CUSTOMER_PREFIX.'%'))
            ->get()->each(fn ($b) => $b->forceDelete());
        \App\Domain\Visits\Models\CustomerVisit::query()
            ->whereHas('customer', fn ($q) => $q->where('customer_code', 'like', self::CUSTOMER_PREFIX.'%'))->delete();
        \App\Domain\TestDrives\Models\TestDrive::query()
            ->whereHas('vehicle', fn ($q) => $q->where('stock_number', 'like', self::STOCK_PREFIX.'%'))->delete();

        VehicleInspection::withTrashed()->where('inspection_number', 'like', self::INSPECTION_PREFIX.'%')->get()
            ->each(fn (VehicleInspection $i) => $i->forceDelete());

        \App\Domain\SalesLeads\Models\SalesLead::withTrashed()->where('lead_number', 'like', self::SALES_LEAD_PREFIX.'%')->get()
            ->each(fn ($l) => $l->forceDelete());

        \App\Domain\Customers\Models\Customer::withTrashed()->where('customer_code', 'like', self::CUSTOMER_PREFIX.'%')->get()
            ->each(fn ($c) => $c->forceDelete());

        PurchaseLead::withTrashed()->where('lead_number', 'like', self::LEAD_PREFIX.'%')->get()
            ->each(fn (PurchaseLead $l) => $l->forceDelete());

        Vehicle::withTrashed()->where('stock_number', 'like', self::STOCK_PREFIX.'%')->get()
            ->each(fn (Vehicle $v) => $v->forceDelete());

        Seller::withTrashed()->where('seller_code', 'like', self::SELLER_PREFIX.'%')->get()
            ->each(fn (Seller $s) => $s->forceDelete());

        Vendor::withTrashed()->where('code', 'like', self::VENDOR_PREFIX.'%')->get()
            ->each(fn (Vendor $v) => $v->forceDelete());

        User::withTrashed()->where('email', 'like', '%'.self::USER_DOMAIN)->get()
            ->each(function (User $u) {
                $u->syncRoles([]);
                $u->forceDelete();
            });

        Branch::withTrashed()->where('code', 'like', self::BRANCH_PREFIX.'%')->get()
            ->each(fn (Branch $b) => $b->forceDelete());
    }

    /** @return array<int, Branch> */
    private function seedBranches(): array
    {
        $branches = [Branch::query()->where('code', 'HO')->first()];

        foreach (['Kanpur', 'Varanasi'] as $i => $city) {
            $branches[] = Branch::query()->create([
                'code' => self::BRANCH_PREFIX.($i + 1),
                'name' => "{$city} Branch",
                'slug' => Str::slug("demo {$city} branch"),
                'city' => $city,
                'state' => 'Uttar Pradesh',
                'phone' => fake()->numerify('9#########'),
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }

        return array_values(array_filter($branches));
    }

    /**
     * @param  array<int, Branch>  $branches
     * @return array<string, array<int, User>>  role name => users
     */
    private function seedEmployees(array $branches): array
    {
        $mgmt = Department::query()->where('code', 'MGMT')->value('id');
        $purchaseDept = Department::query()->where('code', 'PURCHASE')->value('id');
        $inspectDept = Department::query()->where('code', 'INSPECT')->value('id');
        $telecallDept = Department::query()->where('code', 'TELECALL')->value('id');

        $plan = [
            ['Branch Manager', $mgmt, 2],
            ['Purchase Manager', $purchaseDept, 1],
            ['Purchase Executive', $purchaseDept, 3],
            ['Inspector', $inspectDept, 2],
            ['Telecaller', $telecallDept, 2],
            ['RTO Executive', $mgmt, 1],
        ];

        $byRole = [];
        $n = 0;

        foreach ($plan as [$roleName, $deptId, $count]) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role === null) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                $name = fake()->name();
                $branch = $branches[array_rand($branches)];

                $user = User::query()->create([
                    'name' => $name,
                    'email' => Str::slug($name, '.').(++$n).self::USER_DOMAIN,
                    'password' => 'password',
                    'branch_id' => $branch->id,
                    'department_id' => $deptId,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole($role);
                $user->employeeProfile()->create([
                    'employee_code' => self::EMP_PREFIX.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                    'designation' => $roleName,
                    'date_of_joining' => now()->subMonths(random_int(1, 36)),
                ]);

                $byRole[$roleName][] = $user;
            }
        }

        return $byRole;
    }

    /** @return array<int, Seller> */
    private function seedSellers(): array
    {
        $sellers = [];

        for ($i = 1; $i <= 10; $i++) {
            $sellers[] = Seller::query()->create([
                'seller_code' => self::SELLER_PREFIX.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'type' => 'individual',
                'name' => fake()->name(),
                'mobile' => fake()->numerify('9#########'),
                'city' => self::CITIES[array_rand(self::CITIES)],
                'state' => 'Uttar Pradesh',
            ]);
        }

        return $sellers;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @return array<int, Vendor>
     */
    private function seedVendors(array $branches): array
    {
        $plan = [
            ['Sharma Auto Works', 'workshop'],
            ['CarCare Detailing', 'workshop'],
            ['UP Spares & Parts', 'parts'],
            ['QuickRTO Agents', 'rto_agent'],
            ['CityMove Transport', 'transport'],
        ];

        $vendors = [];

        foreach ($plan as $i => [$name, $type]) {
            $vendors[] = Vendor::query()->create([
                'code' => self::VENDOR_PREFIX.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'type' => $type,
                'contact_person' => fake()->name(),
                'phone' => fake()->numerify('9#########'),
                'city' => self::CITIES[array_rand(self::CITIES)],
                'branch_id' => $branches[array_rand($branches)]->id,
                'is_active' => true,
            ]);
        }

        return $vendors;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, User>>  $employees
     * @param  array<int, Seller>  $sellers
     * @return array<int, PurchaseLead>
     */
    private function seedLeads(array $branches, array $employees, array $sellers): array
    {
        $executives = $employees['Purchase Executive'] ?? [];
        $sources = ['website', 'walk_in', 'referral', 'facebook', 'whatsapp', 'manual'];

        // Weighted status mix so the pipeline donut looks realistic.
        $statusPool = [
            PurchaseLeadStatus::New, PurchaseLeadStatus::New, PurchaseLeadStatus::New,
            PurchaseLeadStatus::Contacted, PurchaseLeadStatus::Contacted,
            PurchaseLeadStatus::InspectionScheduled, PurchaseLeadStatus::InspectionCompleted,
            PurchaseLeadStatus::DocumentVerificationPending, PurchaseLeadStatus::ValuationPending,
            PurchaseLeadStatus::Negotiation, PurchaseLeadStatus::PurchaseApprovalPending,
            PurchaseLeadStatus::Purchased, PurchaseLeadStatus::SellerNotInterested,
        ];

        $leads = [];
        $counter = 0;

        // Spread across the last 30 days, weighted towards the last 14.
        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $perDay = $daysAgo <= 14 ? random_int(1, 4) : random_int(0, 2);

            for ($j = 0; $j < $perDay; $j++) {
                $make = array_rand(self::MAKES);
                $model = self::MAKES[$make][array_rand(self::MAKES[$make])];
                $status = $statusPool[array_rand($statusPool)];
                $branch = $branches[array_rand($branches)];
                $seller = $sellers[array_rand($sellers)];
                $exec = $executives !== [] ? $executives[array_rand($executives)] : null;
                $createdAt = Carbon::now()->subDays($daysAgo)->setTime(random_int(9, 19), random_int(0, 59));

                $lead = new PurchaseLead([
                    'lead_number' => self::LEAD_PREFIX.str_pad((string) (++$counter), 5, '0', STR_PAD_LEFT),
                    'seller_id' => $seller->id,
                    'seller_name' => $seller->name,
                    'seller_type' => 'individual',
                    'mobile' => $seller->mobile,
                    'city' => $seller->city,
                    'source' => $sources[array_rand($sources)],
                    'registration_number' => 'UP'.random_int(10, 85).Str::upper(Str::random(2)).random_int(1000, 9999),
                    'make' => $make,
                    'model' => $model,
                    'manufacturing_year' => random_int(2015, 2023),
                    'fuel_type' => ['Petrol', 'Diesel', 'CNG'][array_rand(['Petrol', 'Diesel', 'CNG'])],
                    'transmission' => random_int(0, 1) ? 'Manual' : 'Automatic',
                    'odometer_km' => random_int(12000, 95000),
                    'expected_price' => random_int(300000, 950000),
                    'loan_status' => ['none', 'none', 'active', 'closed_pending_noc'][array_rand([0, 1, 2, 3])],
                    'priority' => ['low', 'normal', 'normal', 'high', 'hot'][array_rand([0, 1, 2, 3, 4])],
                    'branch_id' => $branch->id,
                    'assigned_to' => $exec?->id,
                    'status' => $status->value,
                    'next_follow_up_at' => $this->followUpFor($status, $createdAt),
                    'meta' => ['demo' => true],
                ]);
                $lead->save();
                $lead->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

                $lead->writeStatusHistory(null, PurchaseLeadStatus::New->value, $exec, 'Lead captured (demo)');
                if ($status !== PurchaseLeadStatus::New) {
                    $lead->writeStatusHistory(PurchaseLeadStatus::New->value, $status->value, $exec, 'Demo progression');
                }

                app(VerificationChecklist::class)->seed($lead);

                foreach (range(0, random_int(0, 2)) as $ignored) {
                    $lead->followups()->create([
                        'user_id' => $exec?->id,
                        'contact_mode' => ['call', 'whatsapp', 'visit'][array_rand(['call', 'whatsapp', 'visit'])],
                        'outcome' => 'connected',
                        'remarks' => fake()->sentence(),
                    ]);
                }

                $leads[] = $lead;
            }
        }

        return $leads;
    }

    private function followUpFor(PurchaseLeadStatus $status, Carbon $createdAt): ?Carbon
    {
        if (in_array($status, [PurchaseLeadStatus::Purchased, PurchaseLeadStatus::SellerNotInterested], true)) {
            return null;
        }

        // Mix of overdue and upcoming follow-ups.
        return random_int(0, 1)
            ? Carbon::now()->subDays(random_int(0, 3))->setTime(random_int(10, 18), 0)
            : Carbon::now()->addDays(random_int(1, 5))->setTime(random_int(10, 18), 0);
    }

    /**
     * @param  array<int, PurchaseLead>  $leads
     * @param  array<string, array<int, User>>  $employees
     */
    private function seedInspections(array $leads, array $employees): int
    {
        $inspectors = $employees['Inspector'] ?? [];

        if ($inspectors === []) {
            return 0;
        }

        $checklist = InspectionChecklistItem::query()->where('is_active', true)->orderBy('sort_order')->get()->groupBy('section_key');

        $candidates = array_values(array_filter(
            $leads,
            fn (PurchaseLead $l) => in_array($l->status, [
                PurchaseLeadStatus::InspectionScheduled, PurchaseLeadStatus::InspectionCompleted,
                PurchaseLeadStatus::Negotiation, PurchaseLeadStatus::ValuationPending,
            ], true),
        ));

        $counter = 0;

        foreach (array_slice($candidates, 0, 8) as $lead) {
            $inspector = $inspectors[array_rand($inspectors)];
            $submitted = $lead->status !== PurchaseLeadStatus::InspectionScheduled;

            $inspection = VehicleInspection::query()->create([
                'inspection_number' => self::INSPECTION_PREFIX.str_pad((string) (++$counter), 5, '0', STR_PAD_LEFT),
                'purchase_lead_id' => $lead->id,
                'inspector_id' => $inspector->id,
                'branch_id' => $lead->branch_id,
                'scheduled_at' => $lead->created_at->copy()->addDay(),
                'started_at' => $submitted ? $lead->created_at->copy()->addDays(1)->addHour() : null,
                'completed_at' => $submitted ? $lead->created_at->copy()->addDays(1)->addHours(2) : null,
                'odometer_km' => $lead->odometer_km,
                'overall_grade' => $submitted ? ['A', 'B', 'B', 'C'][array_rand(['A', 'B', 'B', 'C'])] : null,
                'result' => $submitted ? ['recommended', 'recommended_with_repairs'][array_rand(['recommended', 'recommended_with_repairs'])] : null,
                'locked_at' => $submitted ? $lead->created_at->copy()->addDays(1)->addHours(2) : null,
                'status' => $submitted ? 'submitted' : 'scheduled',
            ]);

            $order = 0;
            $totalRepair = 0;

            foreach ($checklist as $sectionKey => $items) {
                $repair = $submitted && random_int(0, 3) === 0 ? random_int(1000, 15000) : 0;
                $totalRepair += $repair;

                $section = $inspection->sections()->create([
                    'key' => $sectionKey,
                    'label' => Str::of($sectionKey)->replace('_', ' ')->title(),
                    'status' => $submitted ? ($repair > 0 ? 'fail' : 'pass') : 'na',
                    'rating' => $submitted ? random_int(3, 5) : null,
                    'repair_estimate' => $repair,
                    'sort_order' => $order++,
                ]);

                foreach ($items as $item) {
                    $section->items()->create([
                        'checklist_item_id' => $item->id,
                        'label' => $item->label,
                        'value' => $submitted ? ($repair > 0 ? 'attention' : 'ok') : 'na',
                        'severity' => $item->is_critical ? 'critical' : null,
                    ]);
                }
            }

            if ($submitted) {
                $inspection->update(['total_repair_estimate' => $totalRepair]);
            }
        }

        return $counter;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<int, Vendor>  $vendors
     */
    private function seedVehicles(array $branches, array $vendors): int
    {
        // Eight sale-eligible vehicles so that after seedBookings() consumes up to
        // five, the public website still lists available stock.
        $statuses = [
            VehicleStatus::InStock, VehicleStatus::InStock,
            VehicleStatus::InspectionPending, VehicleStatus::UnderRefurbishment,
            VehicleStatus::ReadyForSale, VehicleStatus::ReadyForSale,
            VehicleStatus::ReadyForSale, VehicleStatus::ReadyForSale,
            VehicleStatus::Published, VehicleStatus::Published,
            VehicleStatus::Published, VehicleStatus::Published,
            VehicleStatus::Booked, VehicleStatus::Delivered,
        ];

        $expenseService = app(\App\Domain\Inventory\Services\VehicleExpenseService::class);
        $admin = User::query()->where('email', 'admin@car4sales.test')->first();

        foreach ($statuses as $i => $status) {
            $make = array_rand(self::MAKES);
            $model = self::MAKES[$make][array_rand(self::MAKES[$make])];
            $purchase = random_int(300000, 750000);
            $branch = $branches[array_rand($branches)];
            $year = random_int(2016, 2023);
            // Ready-for-sale and published vehicles go live on the public website.
            $onWeb = in_array($status, [VehicleStatus::ReadyForSale, VehicleStatus::Published, VehicleStatus::Booked], true);

            $vehicle = Vehicle::query()->create([
                'stock_number' => self::STOCK_PREFIX.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'registration_number' => 'UP'.random_int(10, 85).Str::upper(Str::random(2)).random_int(1000, 9999),
                'make' => $make,
                'model' => $model,
                'variant' => ['VXI', 'ZXI', 'Sportz', 'Alpha', 'XM'][array_rand(['VXI', 'ZXI', 'Sportz', 'Alpha', 'XM'])],
                'manufacturing_year' => $year,
                'registration_year' => $year,
                'registration_state' => 'Uttar Pradesh',
                'fuel_type' => ['Petrol', 'Diesel'][array_rand(['Petrol', 'Diesel'])],
                'transmission' => random_int(0, 1) ? 'Manual' : 'Automatic',
                'body_type' => ['Hatchback', 'Sedan', 'SUV'][array_rand(['Hatchback', 'Sedan', 'SUV'])],
                'color' => ['White', 'Silver', 'Grey', 'Red', 'Blue'][array_rand(['White', 'Silver', 'Grey', 'Red', 'Blue'])],
                'ownership_serial' => random_int(1, 3),
                'insurance_status' => 'Comprehensive',
                'odometer_km' => random_int(15000, 80000),
                'purchase_price' => $purchase,
                'landed_cost' => $purchase,
                'asking_price' => $purchase + random_int(80000, 160000),
                'branch_id' => $branch->id,
                'status' => $status->value,
                'title' => "{$make} {$model} {$year}",
                'slug' => Str::slug("{$make} {$model} {$year} ".self::STOCK_PREFIX.($i + 1)),
                'description' => "Well-maintained {$make} {$model} ({$year}) with full service history. Inspected and quality-checked.",
                'key_features' => ['Power Steering', 'Air Conditioning', 'Power Windows', 'ABS', 'Airbags'],
                'is_featured' => $i < 3,
                'published_web' => $onWeb,
                'published_mobile' => $onWeb,
            ]);

            $vehicle->writeStatusHistory(null, $status->value, null, 'Demo stock');

            // A couple of approved refurbishment/RTO expenses raise landed cost.
            if ($admin !== null && random_int(0, 1) === 1) {
                foreach ([['refurbishment', random_int(8000, 25000)], ['rto', random_int(3000, 8000)]] as [$cat, $amt]) {
                    $exp = $expenseService->create($vehicle, [
                        'category' => $cat,
                        'amount' => $amt,
                        'description' => 'Demo '.$cat.' expense',
                        'vendor_id' => $vendors !== [] ? $vendors[array_rand($vendors)]->id : null,
                    ], $admin);
                    $expenseService->approve($exp, $admin);
                }
            }
        }

        return count($statuses);
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, User>>  $employees
     */
    private function seedSalesLeads(array $branches, array $employees): int
    {
        $telecallers = $employees['Telecaller'] ?? [];
        $executives = $employees['Sales Executive'] ?? [];
        $sources = ['website', 'walk_in', 'referral', 'facebook', 'google_ads', 'whatsapp', 'marketplace'];
        $statusPool = [
            'new', 'new', 'assigned', 'contacted', 'contacted', 'interested', 'interested',
            'follow_up', 'visit_scheduled', 'negotiation', 'booking', 'lost', 'wrong_number',
        ];
        $vehicles = \App\Domain\Inventory\Models\Vehicle::query()
            ->where('stock_number', 'like', self::STOCK_PREFIX.'%')->pluck('id')->all();
        $lostReasons = \App\Domain\SalesLeads\Models\LeadLostReason::query()->pluck('id')->all();

        $count = 0;
        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $perDay = $daysAgo <= 14 ? random_int(0, 3) : random_int(0, 1);

            for ($j = 0; $j < $perDay; $j++) {
                $name = fake()->name();
                $mobile = '9'.fake()->numerify('#########');
                $createdAt = Carbon::now()->subDays($daysAgo)->setTime(random_int(9, 19), random_int(0, 59));
                $status = $statusPool[array_rand($statusPool)];
                $branch = $branches[array_rand($branches)];
                $telecaller = $telecallers !== [] ? $telecallers[array_rand($telecallers)] : null;
                $isLost = in_array($status, ['lost', 'wrong_number'], true);
                $isOpen = in_array($status, ['new', 'assigned', 'contacted', 'interested', 'follow_up', 'visit_scheduled', 'negotiation'], true);

                $customer = \App\Domain\Customers\Models\Customer::query()->create([
                    'customer_code' => self::CUSTOMER_PREFIX.str_pad((string) (++$count), 5, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'mobile' => $mobile,
                    'city' => self::CITIES[array_rand(self::CITIES)],
                    'branch_id' => $branch->id,
                    'kyc_status' => 'pending',
                    'meta' => ['demo' => true],
                ]);

                $lead = new \App\Domain\SalesLeads\Models\SalesLead([
                    'lead_number' => self::SALES_LEAD_PREFIX.str_pad((string) $count, 5, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'name' => $name,
                    'mobile' => $mobile,
                    'city' => $customer->city,
                    'budget_min' => random_int(3, 6) * 100000,
                    'budget_max' => random_int(7, 12) * 100000,
                    'interested_vehicle_id' => $vehicles !== [] && random_int(0, 1) ? $vehicles[array_rand($vehicles)] : null,
                    'finance_required' => (bool) random_int(0, 1),
                    'exchange_required' => (bool) random_int(0, 1),
                    'source' => $sources[array_rand($sources)],
                    'branch_id' => $branch->id,
                    'telecaller_id' => $status === 'new' ? null : $telecaller?->id,
                    'sales_executive_id' => in_array($status, ['negotiation', 'booking'], true) && $executives !== [] ? $executives[array_rand($executives)]->id : null,
                    'priority' => ['low', 'normal', 'normal', 'high', 'hot'][array_rand([0, 1, 2, 3, 4])],
                    'next_follow_up_at' => $isOpen ? Carbon::now()->addDays(random_int(-2, 5))->setTime(11, 0) : null,
                    'first_response_at' => $status === 'new' ? null : $createdAt->copy()->addHours(random_int(1, 24)),
                    'status' => $status,
                    'lost_reason_id' => $isLost && $lostReasons !== [] ? $lostReasons[array_rand($lostReasons)] : null,
                ]);
                $lead->save();
                $lead->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

                $lead->writeStatusHistory(null, 'new', null, 'Demo lead');
                \App\Domain\SalesLeads\Models\LeadActivity::query()->create([
                    'sales_lead_id' => $lead->id, 'user_id' => $telecaller?->id,
                    'type' => 'created', 'summary' => 'Lead created (demo)',
                ]);

                // A few call logs for contacted+ leads.
                if ($status !== 'new' && $telecaller !== null) {
                    foreach (range(0, random_int(0, 2)) as $ignored) {
                        $lead->followups()->create([
                            'user_id' => $telecaller->id,
                            'channel' => 'call',
                            'call_outcome' => ['connected', 'no_answer', 'interested', 'call_later'][array_rand(['connected', 'no_answer', 'interested', 'call_later'])],
                            'remarks' => fake()->sentence(),
                        ]);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @param  array<string, array<int, User>>  $employees
     */
    private function seedBookings(array $employees): int
    {
        $admin = User::query()->where('email', 'admin@car4sales.test')->first();
        if ($admin === null) {
            return 0;
        }

        $executives = $employees['Sales Executive'] ?? [];
        $createBooking = app(\App\Domain\Bookings\Actions\CreateBookingAction::class);
        $confirmBooking = app(\App\Domain\Bookings\Actions\ConfirmBookingAction::class);
        $payments = app(\App\Domain\Payments\Services\PaymentService::class);
        $finance = app(\App\Domain\Finance\Actions\FinanceApplicationAction::class);
        $lenderIds = \App\Domain\Finance\Models\Lender::query()->pluck('id')->all();
        $visitAction = app(\App\Domain\Visits\Actions\ScheduleVisitAction::class);
        $tdAction = app(\App\Domain\TestDrives\Actions\TestDriveAction::class);

        // Schedule visits & test drives for a spread of demo leads.
        $leads = \App\Domain\SalesLeads\Models\SalesLead::query()
            ->where('lead_number', 'like', self::SALES_LEAD_PREFIX.'%')
            ->whereIn('status', ['interested', 'follow_up', 'visit_scheduled', 'negotiation', 'booking'])
            ->inRandomOrder()->limit(20)->get();

        $vehicles = \App\Domain\Inventory\Models\Vehicle::query()
            ->where('stock_number', 'like', self::STOCK_PREFIX.'%')
            ->whereIn('status', ['ready_for_sale', 'published'])
            ->get();

        foreach ($leads->take(8) as $lead) {
            $visitAction->schedule($lead->fresh(), ['scheduled_at' => now()->addDays(random_int(-3, 3))->setTime(12, 0)], $admin);
        }
        foreach ($leads->take(6) as $lead) {
            if ($vehicles->isNotEmpty()) {
                $tdAction->schedule($lead->fresh(), $vehicles->random(), ['scheduled_at' => now()->addDays(random_int(-2, 4))->setTime(15, 0)], $admin);
            }
        }

        // Create bookings on available vehicles; confirm most of them.
        $count = 0;
        foreach ($vehicles->take(5) as $i => $vehicle) {
            $lead = $leads->get($i) ?? $leads->first();
            if ($lead === null) {
                break;
            }
            $asking = (float) ($vehicle->asking_price ?? 600000);
            $mode = random_int(0, 1) ? 'cash' : 'finance';
            $booking = $createBooking->execute($lead->fresh(), $vehicle->fresh(), [
                'selling_price' => $asking - 10000,
                'discount_amount' => 10000,
                'booking_amount' => 25000,
                'payment_mode' => $mode,
                'sales_executive_id' => $executives !== [] ? $executives[array_rand($executives)]->id : null,
            ], $admin);
            $count++;

            // Confirm ~4 of 5 (admin has unlimited discount authority — no approval).
            if ($i < 4) {
                $confirmBooking->execute($booking, $admin);
                // Payment posts to the customer ledger + a receipt.
                $payments->record($booking->fresh(), ['type' => 'booking', 'amount' => 25000, 'method' => 'upi'], $admin);

                // A finance file for finance-mode bookings.
                if ($mode === 'finance') {
                    $finance->create($booking->fresh(), [
                        'loan_amount' => $asking - 35000,
                        'down_payment' => 25000,
                        'lender_id' => $lenderIds !== [] ? $lenderIds[array_rand($lenderIds)] : null,
                        'tenure_months' => 60,
                    ], $admin);
                }
            }
        }

        return $count;
    }

    /**
     * Deliveries + RTO transfer cases (spec §24-25). Settles the deal, runs the
     * approval checklist, hands over the vehicle and lets the completion hook spawn
     * the RTO case — then advances a couple of cases so the workbench has content.
     *
     * @param  array<string, array<int, User>>  $employees
     * @return array{0: int, 1: int}  [deliveries, rto cases]
     */
    private function seedDeliveries(array $employees): array
    {
        $admin = User::query()->where('email', 'admin@car4sales.test')->first();
        if ($admin === null) {
            return [0, 0];
        }

        $deliveryAction = app(\App\Domain\Deliveries\Actions\DeliveryAction::class);
        $rtoAction = app(\App\Domain\RTO\Actions\RtoCaseAction::class);
        $payments = app(\App\Domain\Payments\Services\PaymentService::class);
        $financeAction = app(\App\Domain\Finance\Actions\FinanceApplicationAction::class);
        $ledgerService = app(\App\Domain\Payments\Services\LedgerService::class);

        $rtoExecutive = $employees['RTO Executive'][0] ?? null;
        $rtoAgent = Vendor::query()->where('code', 'like', self::VENDOR_PREFIX.'%')->where('type', 'rto_agent')->first();

        // Confirmed demo bookings still holding a vehicle and not yet delivered.
        $bookings = \App\Domain\Bookings\Models\Booking::query()
            ->whereHas('customer', fn ($q) => $q->where('customer_code', 'like', self::CUSTOMER_PREFIX.'%'))
            ->whereIn('status', ['confirmed', 'payment_pending', 'finance_pending', 'ready_for_delivery'])
            ->with(['customer', 'vehicle'])
            ->get();

        $deliveries = 0;
        $rtoCases = 0;

        foreach ($bookings->take(3) as $i => $booking) {
            $booking->refresh();

            // KYC clearance for the buyer.
            $booking->customer?->update(['kyc_status' => 'verified']);

            // Settle the ledger so "payment complete" derives true.
            if ($booking->payment_mode === 'finance') {
                $this->disburseDemoFinance($booking, $financeAction, $ledgerService, $admin);
            } else {
                $ledger = $ledgerService->forBooking($booking);
                $outstanding = $ledger !== null ? $ledger->outstanding() : 0.0;
                if ($outstanding > 0) {
                    $payments->record($booking->fresh(), ['type' => 'balance', 'amount' => $outstanding, 'method' => 'bank_transfer'], $admin);
                }
            }

            $delivery = $deliveryAction->create($booking->fresh(), $admin);
            $deliveries++;

            // Booking C stays in approval-pending to demonstrate that state.
            if ($i >= 2) {
                $deliveryAction->refreshChecklist($delivery);
                continue;
            }

            // Complete the manual checklist, approve and hand over.
            $deliveryAction->setManualChecks($delivery, [
                'chk_quality_check' => true, 'chk_insurance' => true, 'chk_rto_papers_signed' => true,
                'chk_accessories' => true, 'chk_cleaned' => true, 'chk_documents_prepared' => true,
            ]);
            $delivery = $deliveryAction->refreshChecklist($delivery);

            if (! $delivery->approvalChecklistComplete()) {
                continue; // Could not fully settle — leave pending rather than force.
            }

            $deliveryAction->approve($delivery, $admin);
            $deliveryAction->complete($delivery->fresh(), $admin, [
                'odometer' => random_int(20000, 90000),
                'fuel_level' => ['Full', 'Half', 'Quarter'][array_rand(['Full', 'Half', 'Quarter'])],
                'dc_keys' => true, 'dc_spare_key' => true, 'dc_rc_copy' => true, 'dc_insurance' => true,
                'dc_invoice' => true, 'dc_tool_kit' => true, 'dc_spare_wheel' => true, 'dc_accessories' => true,
                'remarks' => 'Delivered (demo).',
            ]);

            // The completion hook created the RTO case — enrich it.
            $case = \App\Domain\RTO\Models\RtoCase::query()->where('delivery_id', $delivery->id)->first();
            if ($case !== null) {
                $rtoCases++;
                $rtoAction->assign($case, $rtoExecutive?->id, $rtoAgent?->id, $admin);
                $rtoAction->recordMovement($case, 'Original RC', 'RTO Executive', $admin, 'Showroom', 'Collected for transfer (demo)');
                $rtoAction->addExpense($case, 'transfer_fee', 1500, $admin, 'DEMO-FEE');

                // Advance the first case a few steps down the transfer pipeline.
                if ($i === 0) {
                    $rtoAction->transition($case->fresh(), \App\Domain\RTO\Enums\RtoStatus::SellerDocumentsPending, $admin, 'Seller docs requested (demo)');
                    $rtoAction->transition($case->fresh(), \App\Domain\RTO\Enums\RtoStatus::BuyerDocumentsPending, $admin, 'Buyer docs requested (demo)');
                    $rtoAction->transition($case->fresh(), \App\Domain\RTO\Enums\RtoStatus::SignaturePending, $admin, 'Awaiting signatures (demo)');
                    $rtoAction->addHold($case->fresh(), 5000, 'RC pending — hold against agent (demo)', $admin);
                }
            }
        }

        return [$deliveries, $rtoCases];
    }

    /**
     * Demo sourcing vendors + submissions. Vendor users carry the demo email
     * domain, so clear() removes them (their profile + submissions cascade). No
     * approvals here — that would create a purchase lead outside the demo tags.
     *
     * @param  array<int, Branch>  $branches
     * @return array{0: int, 1: int}  [partners, submissions]
     */
    private function seedVendorPartners(array $branches): array
    {
        $admin = User::query()->where('email', 'admin@car4sales.test')->first();
        if ($admin === null) {
            return [0, 0];
        }

        $registration = app(\App\Domain\VendorSubmissions\Actions\VendorRegistrationAction::class);
        $submissions = app(\App\Domain\VendorSubmissions\Actions\VendorSubmissionAction::class);

        // An activated partner with two submissions, plus one awaiting activation.
        $active = $registration->register([
            'name' => 'Deepak Sharma', 'email' => 'deepak.vendor'.self::USER_DOMAIN, 'password' => 'password',
            'phone' => '9876500011', 'company_name' => 'Deepak Auto Traders', 'city' => 'Lucknow',
        ]);
        $registration->setStatus($active->vendorProfile, \App\Domain\VendorSubmissions\Enums\VendorProfileStatus::Active, $admin);

        $registration->register([
            'name' => 'Sunrise Motors', 'email' => 'sunrise.vendor'.self::USER_DOMAIN, 'password' => 'password',
            'phone' => '9876500022', 'company_name' => 'Sunrise Motors', 'city' => 'Kanpur',
        ]);

        $items = fn () => [
            ['section' => 'Engine', 'label' => 'Engine health', 'result' => 'pass', 'rating' => 4],
            ['section' => 'Brakes', 'label' => 'Brakes & discs', 'result' => 'pass', 'rating' => 4],
            ['section' => 'Exterior', 'label' => 'Body & paint', 'result' => 'fail', 'rating' => 2, 'remarks' => 'Minor dents on left door'],
            ['section' => 'Tyres', 'label' => 'Tyre condition', 'result' => 'na', 'rating' => null],
        ];

        // Pending review — with vehicle photos + a damage shot (required to submit).
        $s1 = $submissions->save(null, [
            'make' => 'Maruti', 'model' => 'Baleno', 'variant' => 'Zeta', 'manufacturing_year' => 2020,
            'registration_number' => 'UP32 CD 4521',
            'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'color' => 'Grey', 'odometer_km' => 42000,
            'ownership_serial' => 1, 'expected_amount' => 545000,
            'overall_remark' => 'Well maintained, single owner.', 'branch_id' => $branches[0]->id ?? null,
            'items' => $items(),
        ], $active->fresh());
        $this->attachDemoMedia($s1, 'gallery', 3);
        $this->attachDemoMedia($s1, 'damage', 1);
        $submissions->submit($s1->fresh(), $active->fresh());

        // Draft (not yet submitted).
        $submissions->save(null, [
            'make' => 'Hyundai', 'model' => 'Creta', 'variant' => 'SX', 'manufacturing_year' => 2019,
            'registration_number' => 'UP32 EF 7788',
            'fuel_type' => 'Diesel', 'transmission' => 'Automatic', 'color' => 'White', 'odometer_km' => 68000,
            'ownership_serial' => 2, 'expected_amount' => 890000,
            'branch_id' => $branches[0]->id ?? null, 'items' => $items(),
        ], $active->fresh());

        // A fully-settled example: approved (→ purchase lead), owner documents
        // verified, and paid.
        $s3 = $submissions->save(null, [
            'make' => 'Tata', 'model' => 'Nexon', 'variant' => 'XZ', 'manufacturing_year' => 2021,
            'registration_number' => 'UP32 GH 1092',
            'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'color' => 'Blue', 'odometer_km' => 31000,
            'ownership_serial' => 1, 'expected_amount' => 720000, 'branch_id' => $branches[0]->id ?? null,
            'items' => $items(),
        ], $active->fresh());
        $this->attachDemoMedia($s3, 'gallery', 2);
        $this->attachDemoMedia($s3, 'damage', 1);
        $submissions->submit($s3->fresh(), $active->fresh());
        $submissions->approve($s3->fresh(), $admin);
        $s3->update([
            'settlement_status' => \App\Domain\VendorSubmissions\Enums\SettlementStatus::Paid->value,
            'owner_name' => 'Rakesh Kumar', 'owner_phone' => '9876540000', 'owner_email' => 'rakesh.owner@example.test',
            'owner_address' => '14 Civil Lines, Lucknow, UP 226001', 'owner_pan' => 'ABCDE1234F',
            'kyc_submitted_at' => now()->subDays(3), 'kyc_approved_at' => now()->subDays(2), 'kyc_approved_by' => $admin->id,
            'bank_account_name' => 'Rakesh Kumar', 'bank_account_number' => '50100123456',
            'bank_ifsc' => 'HDFC0000123', 'bank_name' => 'HDFC Bank', 'payment_requested_at' => now()->subDays(2),
            'payment_amount' => 710000, 'payment_mode' => 'neft', 'payment_reference' => 'UTRDEMO0001',
            'payment_date' => now()->subDay()->toDateString(), 'paid_by' => $admin->id, 'paid_at' => now()->subDay(),
        ]);
        foreach (['rc', 'pan', 'aadhaar', 'noc', 'key_image', 'owner_photo', 'cancelled_cheque'] as $docType) {
            $this->attachDemoMedia($s3, $docType, 1);
        }
        $this->attachDemoMedia($s3, 'payment_proof', 1);

        return [2, 3];
    }

    /**
     * Attach demo images to a submission. All rows point at a single shared
     * placeholder on the private disk, so re-seeding never accumulates files
     * (the media rows cascade away with the submission on clear()).
     */
    private function attachDemoMedia(\App\Domain\VendorSubmissions\Models\VendorSubmission $submission, string $type, int $count): void
    {
        $path = 'vendor-submissions/demo/placeholder.svg';
        $disk = \Illuminate\Support\Facades\Storage::disk('private');

        if (! $disk->exists($path) && is_file(public_path('logo.svg'))) {
            $disk->put($path, (string) file_get_contents(public_path('logo.svg')));
        }

        for ($i = 0; $i < $count; $i++) {
            $submission->media()->create([
                'type' => $type,
                'file_path' => $path,
                'caption' => ucfirst($type).' image '.($i + 1).' (demo)',
                'original_name' => 'demo.svg',
                'mime_type' => 'image/svg+xml',
                'size_bytes' => $disk->exists($path) ? $disk->size($path) : 0,
            ]);
        }
    }

    /**
     * A small inbox of demo notifications for the admin, tagged demo so they are
     * self-cleaning. Written directly (database channel only) to avoid log spam.
     */
    private function seedNotifications(): int
    {
        $admin = User::query()->where('email', 'admin@car4sales.test')->first();
        if ($admin === null) {
            return 0;
        }

        $delivery = \App\Domain\Deliveries\Models\Delivery::query()->where('status', 'delivered')->latest()->first();
        $rto = \App\Domain\RTO\Models\RtoCase::query()->latest()->first();
        $booking = \App\Domain\Bookings\Models\Booking::query()
            ->whereHas('customer', fn ($q) => $q->where('customer_code', 'like', self::CUSTOMER_PREFIX.'%'))
            ->where('status', '!=', 'draft')->latest()->first();

        $items = [
            ['type' => 'booking.confirmed', 'level' => 'success', 'title' => 'Booking confirmed',
                'body' => $booking ? 'Booking '.$booking->booking_number.' has been confirmed.' : 'A booking has been confirmed.',
                'action_url' => $booking ? '/admin/bookings/'.$booking->id : null, 'read' => true, 'age' => 300],
            ['type' => 'delivery.completed', 'level' => 'success', 'title' => 'Vehicle delivered',
                'body' => $delivery ? 'Delivery '.$delivery->delivery_number.' is complete. RTO transfer has begun.' : 'A vehicle was delivered.',
                'action_url' => $delivery ? '/admin/deliveries/'.$delivery->id : null, 'read' => false, 'age' => 180],
            ['type' => 'rto.signature_pending', 'level' => 'info', 'title' => 'RTO Signature Pending',
                'body' => $rto ? 'Transfer case '.$rto->rto_number.' moved to Signature Pending.' : 'An RTO case advanced.',
                'action_url' => $rto ? '/admin/rto-cases/'.$rto->id : null, 'read' => false, 'age' => 90],
            ['type' => 'approval.requested', 'level' => 'warning', 'title' => 'Approval required',
                'body' => 'A discount approval is awaiting your decision.',
                'action_url' => null, 'read' => false, 'age' => 30],
        ];

        $count = 0;
        foreach ($items as $item) {
            $notification = \App\Domain\Notifications\Models\Notification::query()->create([
                'user_id' => $admin->id,
                'type' => $item['type'],
                'level' => $item['level'],
                'title' => $item['title'],
                'body' => $item['body'],
                'action_url' => $item['action_url'],
                'data' => ['demo' => true],
                'branch_id' => $admin->branch_id,
                'read_at' => $item['read'] ? now()->subMinutes($item['age'])->addMinutes(5) : null,
                'created_at' => now()->subMinutes($item['age']),
                'updated_at' => now()->subMinutes($item['age']),
            ]);
            $notification->deliveries()->create([
                'channel' => 'database', 'driver' => 'database', 'status' => 'sent', 'sent_at' => now()->subMinutes($item['age']),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Walk a demo finance file to Disbursed so a finance-mode delivery can settle.
     */
    private function disburseDemoFinance(
        \App\Domain\Bookings\Models\Booking $booking,
        \App\Domain\Finance\Actions\FinanceApplicationAction $financeAction,
        \App\Domain\Payments\Services\LedgerService $ledgerService,
        User $admin,
    ): void {
        $app = \App\Domain\Finance\Models\FinanceApplication::query()->where('booking_id', $booking->id)->first();
        if ($app === null) {
            return;
        }

        $chain = [
            \App\Domain\Finance\Enums\FinanceStatus::FileReady,
            \App\Domain\Finance\Enums\FinanceStatus::Submitted,
            \App\Domain\Finance\Enums\FinanceStatus::LoggedIn,
            \App\Domain\Finance\Enums\FinanceStatus::CreditPending,
            \App\Domain\Finance\Enums\FinanceStatus::Sanctioned,
        ];
        foreach ($chain as $status) {
            if ($app->status->canTransitionTo($status)) {
                $app = $financeAction->transition($app, $status, ['sanction_amount' => $app->loan_amount], $admin);
            }
        }

        $ledger = $ledgerService->forBooking($booking->fresh());
        $outstanding = $ledger !== null ? max($ledger->outstanding(), 0.0) : 0.0;
        if ($outstanding > 0 && in_array($app->status, [
            \App\Domain\Finance\Enums\FinanceStatus::Sanctioned,
            \App\Domain\Finance\Enums\FinanceStatus::AgreementPending,
            \App\Domain\Finance\Enums\FinanceStatus::DisbursementPending,
        ], true)) {
            $financeAction->disburse($app, $outstanding, 'DEMO-UTR', $admin);
        }
    }
}
