<?php

use App\Http\Controllers\Auth\WorkerLoginController;
use App\Http\Controllers\Authenticated\Staff\AttendenceController;
use App\Http\Controllers\Crm\Authenticated\AssignedJobsController;
use App\Http\Controllers\ExpencesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\Staff\Authenticated\StaffController;
use Illuminate\Support\Facades\Route;

Route::post('worker/login', [WorkerLoginController::class, 'login'])
    ->middleware('guest')
    ->name('worker.login');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/worker/logout', [WorkerLoginController::class, 'logout'])
        ->name('worker.logout');
    Route::resource('/worker/attendance', AttendenceController::class);
    Route::resource('/worker/expenses', ExpencesController::class);
    Route::get('/worker/get-expenses', [ExpencesController::class, 'getExpenses']);
    Route::get('/worker/today/attendance', [AttendenceController::class, 'todayAttendance']);

    Route::post('/worker/check-in', [AttendenceController::class, 'checkIn']);

    Route::post('/worker/check-out', [AttendenceController::class, 'checkOut']);
    Route::get('/worker/monthly-stats', [AttendenceController::class, 'getMonthlyStats']);
    Route::resource('/worker/notifications', NotificationsController::class);
    Route::get('/worker/assigned-jobs-count', [AssignedJobsController::class, 'getAssignedJobsCounts']);
    Route::get('/worker/assigned-jobs', [AssignedJobsController::class, 'getAssignedJobs']);
    Route::get('/worker/assigned-jobs/{id}', [AssignedJobsController::class, 'getAssignedJob']);
    Route::put('/worker/assigned-jobs/{id}', [AssignedJobsController::class, 'updateAssignedJobStatus']);
});
