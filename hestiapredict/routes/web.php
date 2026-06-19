<?php

use App\Http\Controllers\DashboardAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard/login');

Route::get('/dashboard/login', [DashboardAuthController::class, 'showLogin'])
    ->name('dashboard.login');
Route::post('/dashboard/login', [DashboardAuthController::class, 'login'])
    ->name('dashboard.login.submit');

Route::middleware(['auth', 'dashboard.admin'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::post('/dashboard/logout', [DashboardAuthController::class, 'logout'])
        ->name('dashboard.logout');
});
