<?php

namespace App\Http\Controllers\Public;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Models\Faq;
use App\Domain\PublicWebsite\Models\Page;
use App\Domain\PublicWebsite\Models\Testimonial;
use App\Domain\PublicWebsite\Services\FinanceEstimator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('public/About', [
            'page' => Page::query()->where('slug', 'about')->first(),
            'stats' => [
                'in_stock' => Vehicle::query()->published()->count(),
                'branches' => Branch::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function branches(): Response
    {
        return Inertia::render('public/Branches', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'city', 'state', 'address', 'phone', 'email', 'latitude', 'longitude']),
        ]);
    }

    public function branchShow(string $slug, \App\Domain\PublicWebsite\Support\PublicVehiclePresenter $presenter): Response
    {
        $branch = Branch::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $vehicles = Vehicle::query()->published()->with(['branch:id,name,city', 'publicMedia'])
            ->where('branch_id', $branch->id)->latest()->limit(12)->get()
            ->map(fn ($v) => $presenter->card($v));

        return Inertia::render('public/BranchShow', [
            'branch' => $branch->only(['id', 'name', 'slug', 'city', 'state', 'address', 'phone', 'email', 'latitude', 'longitude']),
            'vehicles' => $vehicles->values(),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('public/Contact', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'city']),
        ]);
    }

    public function reviews(): Response
    {
        return Inertia::render('public/Reviews', [
            'testimonials' => Testimonial::query()->where('is_approved', true)
                ->orderByDesc('is_featured')->orderBy('sort_order')->get(),
        ]);
    }

    public function faqs(): Response
    {
        return Inertia::render('public/Faqs', [
            'faqs' => Faq::query()->where('is_active', true)->orderBy('category')->orderBy('sort_order')->get(),
        ]);
    }

    public function finance(Request $request, FinanceEstimator $estimator): Response
    {
        $price = (float) $request->integer('price', 500000);

        return Inertia::render('public/Finance', [
            'defaultEstimate' => $estimator->estimate($price),
            'config' => config('car4sales.public.finance'),
        ]);
    }

    public function legal(string $slug): Response
    {
        $known = ['privacy-policy', 'terms', 'refund-policy', 'disclaimer'];
        abort_unless(in_array($slug, $known, true), 404);

        $page = Page::query()->where('slug', $slug)->where('is_published', true)->first();

        return Inertia::render('public/LegalPage', [
            'page' => $page ?? ['slug' => $slug, 'title' => ucwords(str_replace('-', ' ', $slug)), 'body' => '<p>Content coming soon.</p>'],
        ]);
    }
}
