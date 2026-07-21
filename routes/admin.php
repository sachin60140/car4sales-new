<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\InspectionController;
use App\Http\Controllers\Admin\InventoryActionController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LenderController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PurchaseLeadController;
use App\Http\Controllers\Admin\PurchaseWorkflowController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RtoCaseController;
use App\Http\Controllers\Admin\SalesLeadController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TelecallerReportController;
use App\Http\Controllers\Admin\TestDriveController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\VendorPartnerController;
use App\Http\Controllers\Admin\VendorSubmissionController;
use App\Http\Controllers\Admin\VisitController;
use App\Http\Controllers\Admin\WebsiteEnquiryController;
use App\Http\Controllers\Admin\WorkshopController;
use App\Http\Controllers\FileDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('teams', TeamController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('employees', EmployeeController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::get('audit/activity', [AuditLogController::class, 'activity'])->name('audit.activity');
    Route::get('audit/logins', [AuditLogController::class, 'logins'])->name('audit.logins');

    // Vendor-sourced submissions (staff review) + vendor partner activation.
    Route::get('vendor-submissions', [VendorSubmissionController::class, 'index'])->name('vendor-submissions.index');
    Route::get('vendor-submissions/{vendorSubmission}', [VendorSubmissionController::class, 'show'])->name('vendor-submissions.show');
    Route::post('vendor-submissions/{vendorSubmission}/approve', [VendorSubmissionController::class, 'approve'])->name('vendor-submissions.approve');
    Route::post('vendor-submissions/{vendorSubmission}/reject', [VendorSubmissionController::class, 'reject'])->name('vendor-submissions.reject');
    Route::post('vendor-submissions/{vendorSubmission}/verify-document', [VendorSubmissionController::class, 'verifyDocument'])->name('vendor-submissions.verify-document');
    Route::post('vendor-submissions/{vendorSubmission}/approve-kyc', [VendorSubmissionController::class, 'approveKyc'])->name('vendor-submissions.approve-kyc');
    Route::post('vendor-submissions/{vendorSubmission}/reject-kyc', [VendorSubmissionController::class, 'rejectKyc'])->name('vendor-submissions.reject-kyc');
    Route::post('vendor-submissions/{vendorSubmission}/record-payment', [VendorSubmissionController::class, 'recordPayment'])->name('vendor-submissions.record-payment');
    Route::post('vendor-submissions/{vendorSubmission}/confirm-possession', [VendorSubmissionController::class, 'confirmPossession'])->name('vendor-submissions.confirm-possession');
    Route::get('vendor-partners', [VendorPartnerController::class, 'index'])->name('vendor-partners.index');
    Route::get('vendor-partners/create', [VendorPartnerController::class, 'create'])->name('vendor-partners.create');
    Route::post('vendor-partners', [VendorPartnerController::class, 'store'])->name('vendor-partners.store');
    Route::get('vendor-partners/{vendorProfile}/edit', [VendorPartnerController::class, 'edit'])->name('vendor-partners.edit');
    Route::patch('vendor-partners/{vendorProfile}', [VendorPartnerController::class, 'update'])->name('vendor-partners.update');
    Route::post('vendor-partners/{vendorProfile}/documents', [VendorPartnerController::class, 'uploadDocument'])->name('vendor-partners.documents');
    Route::post('vendor-partners/{vendorProfile}/documents/verify', [VendorPartnerController::class, 'verifyDocument'])->name('vendor-partners.documents.verify');
    Route::post('vendor-partners/{vendorProfile}/status', [VendorPartnerController::class, 'setStatus'])->name('vendor-partners.status');

    // Purchase leads + workflow.
    Route::resource('purchase-leads', PurchaseLeadController::class)->only(['index', 'create', 'store', 'show', 'update']);
    Route::post('purchase-leads/{purchaseLead}/transition', [PurchaseLeadController::class, 'transition'])->name('purchase-leads.transition');
    Route::post('purchase-leads/{purchaseLead}/assign', [PurchaseLeadController::class, 'assign'])->name('purchase-leads.assign');
    Route::post('purchase-leads/{purchaseLead}/followup', [PurchaseWorkflowController::class, 'followup'])->name('purchase-leads.followup');
    Route::post('purchase-leads/{purchaseLead}/documents', [PurchaseWorkflowController::class, 'uploadDocument'])->name('purchase-leads.documents');
    Route::post('purchase-leads/{purchaseLead}/verification', [PurchaseWorkflowController::class, 'updateVerification'])->name('purchase-leads.verification');
    Route::post('purchase-leads/{purchaseLead}/valuation', [PurchaseWorkflowController::class, 'saveValuation'])->name('purchase-leads.valuation');
    Route::post('purchase-leads/{purchaseLead}/request-approval', [PurchaseWorkflowController::class, 'requestApproval'])->name('purchase-leads.request-approval');

    Route::post('approvals/{approvalRequest}/decide', [PurchaseWorkflowController::class, 'decideApproval'])->name('approvals.decide');

    Route::post('purchases/{purchase}/agreement', [PurchaseWorkflowController::class, 'generateAgreement'])->name('purchases.agreement');
    Route::post('purchases/{purchase}/payments', [PurchaseWorkflowController::class, 'recordPayment'])->name('purchases.payments');
    Route::post('purchases/{purchase}/possession', [PurchaseWorkflowController::class, 'confirmPossession'])->name('purchases.possession');
    Route::post('seller-payments/{payment}/approve', [PurchaseWorkflowController::class, 'approvePayment'])->name('seller-payments.approve');
    Route::post('seller-payments/{payment}/reverse', [PurchaseWorkflowController::class, 'reversePayment'])->name('seller-payments.reverse');

    // Inspections.
    Route::resource('inspections', InspectionController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('inspections/{inspection}/submit', [InspectionController::class, 'submit'])->name('inspections.submit');
    Route::post('inspections/{inspection}/media', [InspectionController::class, 'uploadMedia'])->name('inspections.media');

    // Inventory (stock).
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('inventory/{vehicle}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::patch('inventory/{vehicle}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::post('inventory/{vehicle}/media', [InventoryActionController::class, 'uploadMedia'])->name('inventory.media');
    Route::delete('inventory/{vehicle}/media/{media}', [InventoryActionController::class, 'deleteMedia'])->name('inventory.media.delete');
    Route::post('inventory/{vehicle}/documents', [InventoryActionController::class, 'uploadDocument'])->name('inventory.documents');
    Route::post('inventory/{vehicle}/expenses', [InventoryActionController::class, 'addExpense'])->name('inventory.expenses');
    Route::post('inventory/{vehicle}/transfer', [InventoryActionController::class, 'transfer'])->name('inventory.transfer');
    Route::post('inventory/{vehicle}/move', [InventoryActionController::class, 'move'])->name('inventory.move');
    Route::post('inventory/{vehicle}/price', [InventoryActionController::class, 'updatePrice'])->name('inventory.price');
    Route::post('inventory/{vehicle}/publish', [InventoryActionController::class, 'publish'])->name('inventory.publish');
    Route::post('inventory/{vehicle}/unpublish', [InventoryActionController::class, 'unpublish'])->name('inventory.unpublish');
    Route::post('vehicle-expenses/{expense}/approve', [InventoryActionController::class, 'approveExpense'])->name('vehicle-expenses.approve');
    Route::post('vehicle-expenses/{expense}/reverse', [InventoryActionController::class, 'reverseExpense'])->name('vehicle-expenses.reverse');

    // Workshop / refurbishment.
    Route::resource('workshop', WorkshopController::class)->only(['index', 'store', 'show'])->parameters(['workshop' => 'workshopJob']);
    Route::post('workshop/{workshopJob}/approve', [WorkshopController::class, 'approve'])->name('workshop.approve');
    Route::post('workshop/{workshopJob}/start', [WorkshopController::class, 'start'])->name('workshop.start');
    Route::post('workshop/{workshopJob}/complete', [WorkshopController::class, 'complete'])->name('workshop.complete');

    // Vendors.
    Route::resource('vendors', VendorController::class)->only(['index', 'store', 'update', 'destroy']);

    // Website enquiries inbox.
    Route::get('website-enquiries', [WebsiteEnquiryController::class, 'index'])->name('website-enquiries.index');
    Route::put('website-enquiries/{enquiry}', [WebsiteEnquiryController::class, 'update'])->name('website-enquiries.update');

    // CRM: customers.
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // CRM: sales leads + telecaller workbench.
    Route::get('sales-leads', [SalesLeadController::class, 'index'])->name('sales-leads.index');
    Route::get('sales-leads/create', [SalesLeadController::class, 'create'])->name('sales-leads.create');
    Route::post('sales-leads', [SalesLeadController::class, 'store'])->name('sales-leads.store');
    Route::get('sales-leads/{salesLead}', [SalesLeadController::class, 'show'])->name('sales-leads.show');
    Route::post('sales-leads/{salesLead}/call', [SalesLeadController::class, 'logCall'])->name('sales-leads.call');
    Route::post('sales-leads/{salesLead}/transition', [SalesLeadController::class, 'transition'])->name('sales-leads.transition');
    Route::post('sales-leads/{salesLead}/assign', [SalesLeadController::class, 'assign'])->name('sales-leads.assign');

    // CRM: telecaller reports.
    Route::get('reports/telecaller', [TelecallerReportController::class, 'index'])->name('reports.telecaller');

    // Sales: visits & test drives (scheduled from a lead).
    Route::get('visits', [VisitController::class, 'index'])->name('visits.index');
    Route::post('sales-leads/{salesLead}/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::post('visits/{visit}/complete', [VisitController::class, 'complete'])->name('visits.complete');
    Route::get('test-drives', [TestDriveController::class, 'index'])->name('test-drives.index');
    Route::post('sales-leads/{salesLead}/test-drives', [TestDriveController::class, 'store'])->name('test-drives.store');
    Route::post('test-drives/{testDrive}/complete', [TestDriveController::class, 'complete'])->name('test-drives.complete');

    // Bookings.
    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{booking}/payment', [BookingController::class, 'payment'])->name('bookings.payment');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'requestCancellation'])->name('bookings.cancel');
    Route::post('booking-cancellations/{cancellation}/approve', [BookingController::class, 'approveCancellation'])->name('bookings.cancel.approve');
    Route::post('booking-cancellations/{cancellation}/refund', [BookingController::class, 'initiateRefund'])->name('bookings.refund.initiate');
    Route::post('refunds/{refund}/pay', [BookingController::class, 'payRefund'])->name('bookings.refund.pay');
    Route::post('bookings/{booking}/invoice', [BookingController::class, 'generateInvoice'])->name('bookings.invoice');
    Route::post('booking-payments/{payment}/reverse', [BookingController::class, 'reversePayment'])->name('bookings.payment.reverse');

    // Finance & Payments.
    Route::resource('lenders', LenderController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('finance', [FinanceController::class, 'store'])->name('finance.store');
    Route::get('finance/{finance}', [FinanceController::class, 'show'])->name('finance.show');
    Route::post('finance/{finance}/transition', [FinanceController::class, 'transition'])->name('finance.transition');
    Route::post('finance/{finance}/disburse', [FinanceController::class, 'disburse'])->name('finance.disburse');

    // Deliveries.
    Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::post('deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('deliveries/{delivery}/refresh-checklist', [DeliveryController::class, 'refreshChecklist'])->name('deliveries.refresh-checklist');
    Route::post('deliveries/{delivery}/checks', [DeliveryController::class, 'setChecks'])->name('deliveries.checks');
    Route::post('deliveries/{delivery}/approve', [DeliveryController::class, 'approve'])->name('deliveries.approve');
    Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');
    Route::post('deliveries/{delivery}/challan', [DeliveryController::class, 'challan'])->name('deliveries.challan');

    // RTO transfer cases.
    Route::get('rto-cases', [RtoCaseController::class, 'index'])->name('rto-cases.index');
    Route::get('rto-cases/{rtoCase}', [RtoCaseController::class, 'show'])->name('rto-cases.show');
    Route::post('rto-cases/{rtoCase}/transition', [RtoCaseController::class, 'transition'])->name('rto-cases.transition');
    Route::post('rto-cases/{rtoCase}/assign', [RtoCaseController::class, 'assign'])->name('rto-cases.assign');
    Route::post('rto-cases/{rtoCase}/movements', [RtoCaseController::class, 'recordMovement'])->name('rto-cases.movements');
    Route::post('rto-cases/{rtoCase}/expenses', [RtoCaseController::class, 'addExpense'])->name('rto-cases.expenses');
    Route::post('rto-cases/{rtoCase}/holds', [RtoCaseController::class, 'addHold'])->name('rto-cases.holds');
    Route::post('rto-holds/{hold}/release', [RtoCaseController::class, 'releaseHold'])->name('rto-cases.holds.release');
    Route::post('rto-cases/{rtoCase}/rc', [RtoCaseController::class, 'uploadRc'])->name('rto-cases.rc');

    // Reports & exports.
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');

    // Notifications inbox.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Document download + private file streaming.
    Route::get('documents/{document}/download', [FileDownloadController::class, 'document'])->name('documents.download');
    Route::get('files/{path}', [FileDownloadController::class, 'file'])->where('path', '.*')->name('files.show');
});
