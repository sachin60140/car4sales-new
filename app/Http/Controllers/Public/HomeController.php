<?php

namespace App\Http\Controllers\Public;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Models\Faq;
use App\Domain\PublicWebsite\Models\Testimonial;
use App\Domain\PublicWebsite\Models\WebsiteBanner;
use App\Domain\PublicWebsite\Support\PublicVehiclePresenter;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(PublicVehiclePresenter $presenter): Response
    {
        $featured = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia'])
            ->where('is_featured', true)->latest()->limit(8)->get()
            ->map(fn ($v) => $presenter->card($v));

        $recent = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia'])
            ->latest()->limit(8)->get()
            ->map(fn ($v) => $presenter->card($v));

        $brands = Vehicle::query()->published()
            ->select('make', DB::raw('COUNT(*) as total'))
            ->groupBy('make')->orderByDesc('total')->limit(12)->get();

        return Inertia::render('public/Home', [
            'banners' => WebsiteBanner::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'featured' => $featured->values(),
            'recent' => $recent->values(),
            'brands' => $brands,
            'budgetBands' => [
                ['label' => 'Under ₹3 Lakh', 'max' => 300000],
                ['label' => '₹3–5 Lakh', 'min' => 300000, 'max' => 500000],
                ['label' => '₹5–8 Lakh', 'min' => 500000, 'max' => 800000],
                ['label' => '₹8–12 Lakh', 'min' => 800000, 'max' => 1200000],
                ['label' => 'Above ₹12 Lakh', 'min' => 1200000],
            ],
            'branches' => Branch::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'city', 'address', 'phone']),
            'testimonials' => Testimonial::query()->where('is_approved', true)->orderByDesc('is_featured')->orderBy('sort_order')->limit(6)->get(),
            'faqs' => Faq::query()->where('is_active', true)->orderBy('sort_order')->limit(6)->get(['id', 'question', 'answer']),
            'stats' => [
                'in_stock' => Vehicle::query()->published()->count(),
                'branches' => Branch::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
