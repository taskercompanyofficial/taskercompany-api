<?php

use App\Http\Controllers\Application\Auth\LoginController;
use App\Http\Controllers\Application\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('/app/register', [RegisterController::class, 'register'])
    ->middleware('guest')
    ->name('app.register');
Route::post('app/login', [LoginController::class, 'login'])
    ->middleware('guest')
    ->name('app.login');
Route::post('app/check-credentials', [LoginController::class, 'checkCredentials'])
    ->middleware('guest')
    ->name('app.check-credentials');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/app/logout', [LoginController::class, 'destroy'])
        ->name('app.logout');
});
