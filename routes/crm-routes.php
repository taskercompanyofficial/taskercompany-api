<?php

use App\Http\Controllers\Authenticated\InventoryController;
use App\Http\Controllers\Authenticated\Staff\AttendenceController;
use App\Http\Controllers\Crm\Auth\LoginController;
use App\Http\Controllers\Crm\Authenticated\AuthorizedBrandsController;
use App\Http\Controllers\Crm\Authenticated\BranchesController;
use App\Http\Controllers\Crm\Authenticated\CategoryController;
use App\Http\Controllers\Crm\Authenticated\ComplaintController;
use App\Http\Controllers\Crm\Authenticated\CrmAttendanceController;
use App\Http\Controllers\Crm\Authenticated\DashboardController;
use App\Http\Controllers\Crm\Authenticated\FetchCortroller;
use App\Http\Controllers\CsoRemarksController;
use App\Http\Controllers\CustomerReviewsController;
use App\Http\Controllers\RoutesMetaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\Authenticated\StaffController;
use App\Http\Controllers\WhatsappCallbackController;

// Auth Routes
Route::middleware('guest')->group(function () {
    Route::post('/crm/check-credentials', [LoginController::class, 'checkCredentials']);
    Route::post('/crm/login', [LoginController::class, 'login']);
    Route::post('/crm/register', [LoginController::class, 'register']);
});


Route::get('/crm/fetch-categories-ids', [FetchCortroller::class, 'fetchCategories']);
Route::get('/crm/fetch-services-ids', [FetchCortroller::class, 'fetchServices']);
Route::get('/crm/fetch-authorized-brands', [FetchCortroller::class, 'fetchAuthorizedBrands']);
Route::get('/fetch-authorized-brands', [FetchCortroller::class, 'fetchAuthorizedBrands']);
Route::get('/crm/fetch-branches', [FetchCortroller::class, 'fetchBranches']);
Route::get('/crm/fetch-states', [FetchCortroller::class, 'fetchStates']);
Route::get('/crm/fetch-cities', [FetchCortroller::class, 'fetchCities']);
Route::get('/crm/fetch-countries', [FetchCortroller::class, 'fetchCountries']);
Route::match(['get', 'post'], '/whatsapp/callback', [WhatsappCallbackController::class, 'handleCallback']);
Route::post('/crm/complaints/create', [ComplaintController::class, 'store']);
Route::resource('/routes_meta', RoutesMetaController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/crm/complaints/cancel/{id}', [ComplaintController::class, 'cancleComplaint']);
    Route::resource('/crm/staff', StaffController::class);
    Route::resource('/crm/branches', BranchesController::class);
    Route::resource('/crm/authorized-brands', AuthorizedBrandsController::class);
    Route::resource('/crm/categories', CategoryController::class);
    Route::post('/complaints/send-message-to-customer/{to}', [ComplaintController::class, 'sendMessage']);
    Route::resource('/crm/complaints', ComplaintController::class);
    Route::put('/complaints/schedule', [ComplaintController::class, 'scedualeComplaint']);
    Route::get('/crm/fetch-workers', [FetchCortroller::class, 'fetchWorkers']);
    Route::get('/crm/fetch-branches-ids', [FetchCortroller::class, 'fetchBranches']);
    Route::get('/crm/dashboard-chart-data', [DashboardController::class, 'complaintByStatus']);
    Route::resource('/crm/attendance', CrmAttendanceController::class);
    Route::get('/crm/dashboard-status-data', [DashboardController::class, 'getStatusChartData']);
    Route::get('/crm/dashboard-complaints-by-brand', [DashboardController::class, 'getComplaintStatusByBrand']);
    Route::get('/crm/dashboard-get-complaints', [DashboardController::class, 'getComplaints']);
    Route::get('/crm/complaint-history/{id}', [ComplaintController::class, 'getComplaintHistory']);
    Route::resource('/crm/inventory', InventoryController::class);
    Route::get('/crm/daily-attendance-stats', [CrmAttendanceController::class, 'getDailyStats']);
    Route::post('/crm/attendance/mark-present/{id}', [CrmAttendanceController::class, 'markPresent']);
    Route::post('/crm/attendance/mark-absent/{id}', [CrmAttendanceController::class, 'markAbsent']);
    Route::get('/crm/attendance/by-user/{id}', [AttendenceController::class, 'attendenceByUser']);
    Route::get('/crm/complaints/technician-reached-on-site/{id}', [ComplaintController::class, 'technicianReachedOnSite']);
    Route::apiResource('/crm/customer-reviews', CustomerReviewsController::class);
    Route::get('/crm/complaint/customer-reviews/{complaintId}', [CustomerReviewsController::class, 'fetch']);
    Route::apiResource('/crm/cso-remarks', CsoRemarksController::class);
    Route::get('/crm/cso-remarks/{complaintId}', [CsoRemarksController::class, 'index']);
});
