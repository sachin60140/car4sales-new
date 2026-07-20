<?php

namespace App\Http\Controllers\Public;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Services\FinanceEstimator;
use App\Domain\PublicWebsite\Support\PublicVehiclePresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleListingController extends Controller
{
    public function index(Request $request, PublicVehiclePresenter $presenter): Response
    {
        $query = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia']);

        $this->applyFilters($query, $request);

        $sort = $request->string('sort')->toString();
        match ($sort) {
            'price_asc' => $query->orderBy('asking_price'),
            'price_desc' => $query->orderByDesc('asking_price'),
            'year_desc' => $query->orderByDesc('manufacturing_year'),
            'km_asc' => $query->orderBy('odometer_km'),
            default => $query->latest(),
        };

        $vehicles = $query->paginate(12)->withQueryString()
            ->through(fn (Vehicle $v) => $presenter->card($v));

        return Inertia::render('public/cars/Index', [
            'vehicles' => $vehicles,
            'filterOptions' => $this->filterOptions(),
            'filters' => $request->only([
                'search', 'make', 'model', 'fuel_type', 'transmission', 'body_type',
                'ownership', 'branch_id', 'color', 'price_min', 'price_max', 'year_min',
                'year_max', 'km_max', 'availability', 'sort', 'view',
            ]),
        ]);
    }

    public function show(Request $request, string $slug, PublicVehiclePresenter $presenter, FinanceEstimator $finance): Response
    {
        $vehicle = Vehicle::query()->published()->with(['branch', 'publicMedia'])
            ->where('slug', $slug)
            ->firstOrFail();

        $similar = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia'])
            ->where('id', '!=', $vehicle->id)
            ->where(fn ($q) => $q->where('make', $vehicle->make)->orWhere('body_type', $vehicle->body_type))
            ->limit(4)->get()
            ->map(fn ($v) => $presenter->card($v));

        return Inertia::render('public/cars/Show', [
            'vehicle' => $presenter->detail($vehicle),
            'financeEstimate' => $vehicle->asking_price ? $finance->estimate((float) $vehicle->asking_price) : null,
            'similar' => $similar->values(),
        ]);
    }

    public function compare(Request $request, PublicVehiclePresenter $presenter): Response
    {
        $ids = collect(explode(',', $request->string('ids')->toString()))
            ->filter()->map(fn ($id) => (int) $id)->take(3);

        $vehicles = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia'])
            ->whereIn('id', $ids)->get()
            ->map(fn (Vehicle $v) => $presenter->detail($v));

        return Inertia::render('public/Compare', ['vehicles' => $vehicles->values()]);
    }

    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('make', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")
                ->orWhere('variant', 'like', "%{$s}%")
                ->orWhere('title', 'like', "%{$s}%")))
            ->when($request->string('make')->toString(), fn ($q, $v) => $q->where('make', $v))
            ->when($request->string('model')->toString(), fn ($q, $v) => $q->where('model', $v))
            ->when($request->string('fuel_type')->toString(), fn ($q, $v) => $q->where('fuel_type', $v))
            ->when($request->string('transmission')->toString(), fn ($q, $v) => $q->where('transmission', $v))
            ->when($request->string('body_type')->toString(), fn ($q, $v) => $q->where('body_type', $v))
            ->when($request->string('color')->toString(), fn ($q, $v) => $q->where('color', $v))
            ->when($request->integer('ownership'), fn ($q, $v) => $q->where('ownership_serial', $v))
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->integer('price_min'), fn ($q, $v) => $q->where('asking_price', '>=', $v))
            ->when($request->integer('price_max'), fn ($q, $v) => $q->where('asking_price', '<=', $v))
            ->when($request->integer('year_min'), fn ($q, $v) => $q->where('manufacturing_year', '>=', $v))
            ->when($request->integer('year_max'), fn ($q, $v) => $q->where('manufacturing_year', '<=', $v))
            ->when($request->integer('km_max'), fn ($q, $v) => $q->where('odometer_km', '<=', $v))
            ->when($request->string('availability')->toString() === 'available', fn ($q) => $q->whereIn('status', ['ready_for_sale', 'published']));
    }

    /** @return array<string, mixed> */
    private function filterOptions(): array
    {
        $base = Vehicle::query()->published();

        return [
            'makes' => (clone $base)->select('make')->distinct()->orderBy('make')->pluck('make'),
            'models' => (clone $base)->select('model')->distinct()->orderBy('model')->pluck('model'),
            'fuelTypes' => (clone $base)->select('fuel_type')->whereNotNull('fuel_type')->distinct()->pluck('fuel_type'),
            'transmissions' => (clone $base)->select('transmission')->whereNotNull('transmission')->distinct()->pluck('transmission'),
            'bodyTypes' => (clone $base)->select('body_type')->whereNotNull('body_type')->distinct()->pluck('body_type'),
            'colors' => (clone $base)->select('color')->whereNotNull('color')->distinct()->pluck('color'),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'yearRange' => [
                'min' => (int) ((clone $base)->min('manufacturing_year') ?? 2010),
                'max' => (int) ((clone $base)->max('manufacturing_year') ?? (int) date('Y')),
            ],
            'priceRange' => [
                'min' => (int) ((clone $base)->min('asking_price') ?? 0),
                'max' => (int) ((clone $base)->max('asking_price') ?? 2000000),
            ],
        ];
    }
}
