<?php

use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\InspectionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PurchaseLeadController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RtoCaseController;
use App\Http\Controllers\Api\V1\SalesLeadController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::middleware(['auth:sanctum', 'active', 'throttle:120,1'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/device/push-token', [AuthController::class, 'updatePushToken'])->name('auth.push-token');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Purchase leads.
        Route::get('purchase-leads', [PurchaseLeadController::class, 'index'])->name('purchase-leads.index');
        Route::post('purchase-leads', [PurchaseLeadController::class, 'store'])->name('purchase-leads.store');
        Route::get('purchase-leads/{purchaseLead}', [PurchaseLeadController::class, 'show'])->name('purchase-leads.show');
        Route::post('purchase-leads/{purchaseLead}/followups', [PurchaseLeadController::class, 'followup'])->name('purchase-leads.followups');
        Route::post('purchase-leads/{purchaseLead}/transition', [PurchaseLeadController::class, 'transition'])->name('purchase-leads.transition');

        // Inspections.
        Route::get('inspections', [InspectionController::class, 'index'])->name('inspections.index');
        Route::get('inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');
        Route::patch('inspections/{inspection}', [InspectionController::class, 'update'])->name('inspections.update');
        Route::post('inspections/{inspection}/submit', [InspectionController::class, 'submit'])->name('inspections.submit');
        Route::post('inspections/{inspection}/media', [InspectionController::class, 'uploadMedia'])->name('inspections.media');

        // Approval inbox.
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/{approvalRequest}/decide', [ApprovalController::class, 'decide'])->name('approvals.decide');

        // Inventory / stock (mobile stock executive).
        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
        Route::post('vehicles/{vehicle}/media', [VehicleController::class, 'uploadMedia'])->name('vehicles.media');
        Route::post('vehicles/{vehicle}/expenses', [VehicleController::class, 'addExpense'])->name('vehicles.expenses');

        // Sales leads / telecaller (mobile).
        Route::get('sales-leads', [SalesLeadController::class, 'index'])->name('sales-leads.index');
        Route::get('follow-ups', [SalesLeadController::class, 'followUpQueue'])->name('follow-ups.queue');
        Route::get('sales-leads/performance', [SalesLeadController::class, 'performance'])->name('sales-leads.performance');
        Route::get('sales-leads/{salesLead}', [SalesLeadController::class, 'show'])->name('sales-leads.show');
        Route::post('sales-leads/{salesLead}/call-log', [SalesLeadController::class, 'logCall'])->name('sales-leads.call-log');
        Route::post('sales-leads/{salesLead}/transition', [SalesLeadController::class, 'transition'])->name('sales-leads.transition');
        Route::post('sales-leads/{salesLead}/visits', [SalesLeadController::class, 'scheduleVisit'])->name('sales-leads.visits');
        Route::post('sales-leads/{salesLead}/test-drives', [SalesLeadController::class, 'scheduleTestDrive'])->name('sales-leads.test-drives');

        // Bookings (mobile sales executive).
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');

        // Finance (mobile finance executive).
        Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('finance/{finance}', [FinanceController::class, 'show'])->name('finance.show');
        Route::post('finance/{finance}/transition', [FinanceController::class, 'transition'])->name('finance.transition');
        Route::post('finance/{finance}/disburse', [FinanceController::class, 'disburse'])->name('finance.disburse');

        // Deliveries (mobile delivery executive).
        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::post('deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{delivery}/checks', [DeliveryController::class, 'setChecks'])->name('deliveries.checks');
        Route::post('deliveries/{delivery}/approve', [DeliveryController::class, 'approve'])->name('deliveries.approve');
        Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');

        // RTO transfer cases (mobile RTO executive).
        Route::get('rto-cases', [RtoCaseController::class, 'index'])->name('rto-cases.index');
        Route::get('rto-cases/{rtoCase}', [RtoCaseController::class, 'show'])->name('rto-cases.show');
        Route::post('rto-cases/{rtoCase}/transition', [RtoCaseController::class, 'transition'])->name('rto-cases.transition');
        Route::post('rto-cases/{rtoCase}/movements', [RtoCaseController::class, 'recordMovement'])->name('rto-cases.movements');
        Route::post('rto-cases/{rtoCase}/expenses', [RtoCaseController::class, 'addExpense'])->name('rto-cases.expenses');

        // Reports (managers).
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');

        // Notifications (all roles).
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    });
});
