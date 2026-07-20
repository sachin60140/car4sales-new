<?php

namespace App\Http\Controllers\Public;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Support\PublicVehiclePresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FavouriteController extends Controller
{
    public function index(Request $request, PublicVehiclePresenter $presenter): Response
    {
        $ids = DB::table('vehicle_favourites')
            ->where('session_id', $request->session()->getId())
            ->pluck('vehicle_id');

        $vehicles = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia'])
            ->whereIn('id', $ids)->get()
            ->map(fn ($v) => $presenter->card($v));

        return Inertia::render('public/Favourites', ['vehicles' => $vehicles->values()]);
    }

    public function toggle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $session = $request->session()->getId();

        $exists = DB::table('vehicle_favourites')
            ->where('session_id', $session)->where('vehicle_id', $vehicle->id)->exists();

        if ($exists) {
            DB::table('vehicle_favourites')->where('session_id', $session)->where('vehicle_id', $vehicle->id)->delete();
        } else {
            DB::table('vehicle_favourites')->insert([
                'session_id' => $session, 'vehicle_id' => $vehicle->id, 'created_at' => now(),
            ]);
        }

        return back();
    }
}
