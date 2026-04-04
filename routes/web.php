<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:web')
        ->name('logout');

    Route::middleware(['auth:web'])->group(function (): void {
        Route::redirect('/', '/admin/dashboard')->name('index');
        Route::get('dashboard', fn () => Inertia::render('Admin/Dashboard'))
            ->middleware('can:view admin dashboard')
            ->name('dashboard');

        Route::prefix('whatsapp')
            ->name('whatsapp.')
            ->middleware('role:admin')
            ->controller(WhatsAppController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('{device}/replay-scan', 'replayScan')->name('replay-scan');
                Route::post('{device}/send', 'send')->name('send');
                Route::delete('{device}', 'destroy')->name('destroy');
            });

        Route::get('users/records', [UserController::class, 'records'])->name('users.records');
        Route::get('users/{user}/activity-logs', [UserController::class, 'activityLogs'])->name('users.activity-logs');
        Route::resource('users', UserController::class)->except(['show']);

        Route::get('roles/records', [RoleController::class, 'records'])->name('roles.records');
        Route::get('roles/{role}/activity-logs', [RoleController::class, 'activityLogs'])->name('roles.activity-logs');
        Route::resource('roles', RoleController::class)->except(['show']);

        Route::get('plans/records', [PlanController::class, 'records'])->name('plans.records');
        Route::get('plans/{plan}/activity-logs', [PlanController::class, 'activityLogs'])->name('plans.activity-logs');
        Route::resource('plans', PlanController::class)->except(['show']);

        Route::get('activity-logs/records', [ActivityLogController::class, 'records'])->name('activity-logs.records');
        Route::resource('activity-logs', ActivityLogController::class)->only(['index']);

        Route::get('settings', fn () => Inertia::render('Admin/Settings'))->name('settings');
        Route::put('settings/system', [SystemSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/brand-assets', [SystemSettingsController::class, 'uploadBrandAsset'])->name('settings.brand-assets.upload');

        Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
});
