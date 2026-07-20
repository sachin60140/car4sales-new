<?php

use App\Http\Controllers\Public\EnquiryController;
use App\Http\Controllers\Public\FavouriteController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SellCarController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\VehicleListingController;
use Illuminate\Support\Facades\Route;

/*
| Public-facing dealership website. Accessible without employee authentication.
*/

Route::get('/', HomeController::class)->name('home');

// Inventory browsing.
Route::get('/cars', [VehicleListingController::class, 'index'])->name('cars.index');
Route::get('/compare', [VehicleListingController::class, 'compare'])->name('cars.compare');
Route::get('/cars/{slug}', [VehicleListingController::class, 'show'])->name('cars.show');

// Sell your car.
Route::get('/sell-your-car', [SellCarController::class, 'create'])->name('sell-car');
Route::post('/sell-your-car', [SellCarController::class, 'store'])->name('sell-car.store');

// Finance.
Route::get('/finance', [PageController::class, 'finance'])->name('finance');

// Content pages.
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/branches', [PageController::class, 'branches'])->name('branches');
Route::get('/branches/{slug}', [PageController::class, 'branchShow'])->name('branches.show');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/reviews', [PageController::class, 'reviews'])->name('reviews');
Route::get('/faqs', [PageController::class, 'faqs'])->name('faqs');

// Legal pages.
Route::get('/{slug}', [PageController::class, 'legal'])
    ->whereIn('slug', ['privacy-policy', 'terms', 'refund-policy', 'disclaimer'])
    ->name('legal');

// Favourites (session-based).
Route::get('/favourites', [FavouriteController::class, 'index'])->name('favourites');
Route::post('/favourites/{vehicle}', [FavouriteController::class, 'toggle'])->name('favourites.toggle');

// Enquiries + OTP.
Route::post('/enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');
Route::post('/enquiries/otp/request', [EnquiryController::class, 'requestOtp'])->name('enquiries.otp.request');
Route::post('/enquiries/otp/verify', [EnquiryController::class, 'verifyOtp'])->name('enquiries.otp.verify');

// SEO.
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
