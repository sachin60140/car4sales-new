<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;

function publishedVehicle(array $overrides = []): Vehicle
{
    return Vehicle::query()->create(array_merge([
        'stock_number' => 'STK-'.fake()->unique()->numerify('P####'),
        'make' => 'Maruti', 'model' => 'Swift', 'variant' => 'VXI',
        'manufacturing_year' => 2020,
        'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'body_type' => 'Hatchback',
        'color' => 'White', 'odometer_km' => 35000, 'ownership_serial' => 1,
        'chassis_number' => 'CH'.fake()->unique()->numerify('#########'),
        'engine_number' => 'EN'.fake()->unique()->numerify('#########'),
        'purchase_price' => 400000, 'landed_cost' => 450000, 'minimum_selling_price' => 520000,
        'asking_price' => 550000,
        'status' => VehicleStatus::Published->value,
        'slug' => 'maruti-swift-'.fake()->unique()->numerify('####'),
        'title' => 'Maruti Swift 2020',
        'published_web' => true,
    ], $overrides));
}

it('renders the public home page', function () {
    $this->get('/')->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/Home'));
});

it('lists only published vehicles on the public listing', function () {
    $live = publishedVehicle();
    $draft = publishedVehicle(['published_web' => false, 'status' => VehicleStatus::InStock->value]);

    $this->get('/cars')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/cars/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $live->id));
});

it('never exposes internal fields on the public vehicle payload', function () {
    $vehicle = publishedVehicle();

    $this->get("/cars/{$vehicle->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/cars/Show')
            ->where('vehicle.title', 'Maruti Swift 2020')
            ->where('vehicle.asking_price', '550000.00')
            ->missing('vehicle.chassis_number')
            ->missing('vehicle.engine_number')
            ->missing('vehicle.purchase_price')
            ->missing('vehicle.landed_cost')
            ->missing('vehicle.minimum_selling_price'));
});

it('404s an unpublished vehicle detail page', function () {
    $vehicle = publishedVehicle(['published_web' => false, 'status' => VehicleStatus::InStock->value]);

    $this->get("/cars/{$vehicle->slug}")->assertNotFound();
});

it('filters the listing by make and price', function () {
    publishedVehicle(['make' => 'Maruti', 'asking_price' => 400000, 'slug' => 'a-'.fake()->numerify('###')]);
    publishedVehicle(['make' => 'Honda', 'model' => 'City', 'asking_price' => 900000, 'slug' => 'b-'.fake()->numerify('###')]);

    $this->get('/cars?make=Honda')
        ->assertInertia(fn ($page) => $page->has('vehicles.data', 1)->where('vehicles.data.0.make', 'Honda'));

    $this->get('/cars?price_max=500000')
        ->assertInertia(fn ($page) => $page->has('vehicles.data', 1)->where('vehicles.data.0.make', 'Maruti'));
});

it('serves the sitemap and robots files', function () {
    publishedVehicle(['slug' => 'sitemap-car-1']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('/cars/sitemap-car-1', false);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap:', false)
        ->assertSee('Disallow: /admin', false);
});

it('renders a legal page', function () {
    $this->get('/privacy-policy')->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/LegalPage')->where('page.slug', 'privacy-policy'));
});

it('toggles a vehicle favourite in the session', function () {
    $vehicle = publishedVehicle();

    $this->post("/favourites/{$vehicle->id}")->assertRedirect();
    $this->assertDatabaseHas('vehicle_favourites', ['vehicle_id' => $vehicle->id]);
});
