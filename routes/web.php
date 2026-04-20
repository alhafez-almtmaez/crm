<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\AbsenceRuleController;
use App\Http\Controllers\Admin\MessageTemplateController;
use App\Http\Controllers\Admin\CenterController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\EvaluationController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/admin');

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

        Route::get('centers/records', [CenterController::class, 'records'])->name('centers.records');
        Route::get('centers/{center}/activity-logs', [CenterController::class, 'activityLogs'])->name('centers.activity-logs');
        Route::resource('centers', CenterController::class)->except(['show']);

        Route::get('groups/records', [GroupController::class, 'records'])->name('groups.records');
        Route::get('groups/{group}/activity-logs', [GroupController::class, 'activityLogs'])->name('groups.activity-logs');
        Route::get('centers/{center}/groups', [GroupController::class, 'byCenter'])->name('groups.by-center');
        Route::resource('groups', GroupController::class)->except(['show']);

        Route::get('students/records', [StudentController::class, 'records'])->name('students.records');
        Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import');
        Route::get('students/{student}/activity-logs', [StudentController::class, 'activityLogs'])->name('students.activity-logs');
        Route::post('students/{student}/freeze', [StudentController::class, 'freeze'])->name('students.freeze');
        Route::post('students/{student}/unfreeze', [StudentController::class, 'unfreeze'])->name('students.unfreeze');
        Route::post('students/{student}/congratulatory', [StudentController::class, 'congratulatory'])->name('students.congratulatory');
        Route::resource('students', StudentController::class)->except(['show']);

        Route::get('evaluations/records', [EvaluationController::class, 'records'])->name('evaluations.records');
        Route::post('evaluations/{evaluation}/absence-alerts', [EvaluationController::class, 'sendAbsenceAlerts'])->name('evaluations.absence-alerts');
        Route::resource('evaluations', EvaluationController::class)->except(['show']);

        Route::get('absence-rules/records', [AbsenceRuleController::class, 'records'])->name('absence-rules.records');
        Route::resource('absence-rules', AbsenceRuleController::class)->except(['show']);

        Route::get('message-templates/records', [MessageTemplateController::class, 'records'])->name('message-templates.records');
        Route::resource('message-templates', MessageTemplateController::class)->except(['show']);

        Route::get('activity-logs/records', [ActivityLogController::class, 'records'])->name('activity-logs.records');
        Route::resource('activity-logs', ActivityLogController::class)->only(['index']);

        Route::get('settings', fn () => Inertia::render('Admin/Settings'))->name('settings');
        Route::put('settings/system', [SystemSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/brand-assets', [SystemSettingsController::class, 'uploadBrandAsset'])->name('settings.brand-assets.upload');

        Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
});
