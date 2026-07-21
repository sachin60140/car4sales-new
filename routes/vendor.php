<?php

use App\Http\Controllers\Vendor\SubmissionController;
use App\Http\Controllers\Vendor\SubmissionMediaController;
use App\Http\Controllers\Vendor\VendorDashboardController;
use App\Http\Controllers\Vendor\VendorRegistrationController;
use Illuminate\Support\Facades\Route;

// Self-service vendor registration (public).
Route::middleware('guest')->group(function () {
    Route::get('vendor/register', [VendorRegistrationController::class, 'create'])->name('vendor.register');
    Route::post('vendor/register', [VendorRegistrationController::class, 'store'])->name('vendor.register.store');
});

// Submission image streaming — the owner vendor or a staff reviewer (auth only,
// authorised per-submission), so it sits outside the vendor-role group.
Route::get('submission-media/{media}', [SubmissionMediaController::class, 'show'])
    ->middleware('auth')->name('submission-media.view');

// Pre-filled agreement PDF — downloadable by the owner vendor or a staff reviewer.
Route::get('submission-agreement/{submission}', [SubmissionController::class, 'agreement'])
    ->middleware('auth')->name('submission-agreement.download');

// Vendor partner portal.
Route::middleware(['auth', 'verified', 'role:Vendor Partner'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/', VendorDashboardController::class)->name('dashboard');

    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::get('submissions/{submission}/edit', [SubmissionController::class, 'edit'])->name('submissions.edit');
    Route::put('submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
    Route::post('submissions/{submission}/media', [SubmissionController::class, 'uploadMedia'])->name('submissions.media');
    Route::delete('submission-media/{media}', [SubmissionController::class, 'deleteMedia'])->name('submissions.media.delete');
    Route::post('submissions/{submission}/submit', [SubmissionController::class, 'submit'])->name('submissions.submit');
    Route::post('submissions/{submission}/owner-kyc', [SubmissionController::class, 'submitKyc'])->name('submissions.owner-kyc');
    Route::post('submissions/{submission}/request-payment', [SubmissionController::class, 'requestPayment'])->name('submissions.request-payment');
});
