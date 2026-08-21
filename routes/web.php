<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceReminderController;
use App\Http\Controllers\MaintenanceScheduleController;
use App\Http\Controllers\MarineMapController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSecurityController;
use App\Http\Controllers\SourceCredentialController;
use App\Http\Controllers\SourceDiagnosticsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VesselController;
use App\Http\Controllers\VesselEquipmentController;
use App\Models\AuditEvent;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
foreach (['about', 'features', 'getting-started', 'faq', 'regions', 'safety', 'data-sources', 'status', 'support', 'accessibility', 'security', 'terms', 'privacy', 'cookies', 'acceptable-use', 'location-notice', 'uploaded-content', 'account-data', 'copyright', 'open-source', 'emergency', 'changelog'] as $page) {
    Route::view('/'.$page, 'public.page', ['page' => $page])->name('public.'.$page);
}

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth', 'auth.session'])->name('logout');

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/email/verify', function (Request $request) {
        $user = $request->user();

        if (app()->environment('local') && config('marine.local_auto_verify_email') && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            AuditEvent::create(['actor_id' => $user->id, 'channel' => 'system', 'action' => 'email.auto_verified_local', 'target_type' => $user::class, 'target_id' => (string) $user->id, 'occurred_at' => now()]);

            return redirect()->route('dashboard');
        }

        return view('auth.verify');
    })->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('dashboard');
    })->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent.');
    })->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/navigation', [MarineMapController::class, 'navigation'])->name('navigation');
    Route::get('/map/data', [MarineMapController::class, 'data'])->middleware('throttle:60,1')->name('map.data');
    Route::post('/map/position', [MarineMapController::class, 'storePosition'])->middleware('throttle:20,1')->name('map.position.store');
    Route::patch('/vessels/{vessel}/restore', [VesselController::class, 'restore'])
        ->middleware('throttle:10,1')
        ->name('vessels.restore');
    Route::resource('vessels', VesselController::class);
    Route::get('/vessels/{vessel}/equipment', [VesselEquipmentController::class, 'index'])
        ->name('vessels.equipment.index');
    Route::post('/vessels/{vessel}/equipment', [VesselEquipmentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('vessels.equipment.store');
    Route::get('/equipment/{equipment}/edit', [VesselEquipmentController::class, 'edit'])
        ->name('equipment.edit');
    Route::patch('/equipment/{equipment}', [VesselEquipmentController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('equipment.update');
    Route::get('/vessels/{vessel}/maintenance', [MaintenanceScheduleController::class, 'index'])
        ->name('vessels.maintenance.index');
    Route::post('/vessels/{vessel}/maintenance', [MaintenanceScheduleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('vessels.maintenance.store');
    Route::get('/maintenance/reminders', [MaintenanceReminderController::class, 'index'])
        ->name('maintenance.reminders.index');
    Route::get('/maintenance/{schedule}/edit', [MaintenanceScheduleController::class, 'edit'])
        ->name('maintenance.edit');
    Route::patch('/maintenance/{schedule}', [MaintenanceScheduleController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('maintenance.update');
    Route::post('/maintenance/{schedule}/completions', [MaintenanceScheduleController::class, 'storeCompletion'])
        ->middleware('throttle:20,1')
        ->name('maintenance.completions.store');
    Route::post('/maintenance/completions/{completion}/reversal', [MaintenanceScheduleController::class, 'reverseCompletion'])
        ->middleware('throttle:10,1')
        ->name('maintenance.completions.reversal.store');
    Route::get('/vessels/{vessel}/maintenance/reminders/history', [MaintenanceReminderController::class, 'history'])
        ->name('vessels.maintenance.reminders.history');
    Route::post('/maintenance/reminders/{occurrence}/acknowledge', [MaintenanceReminderController::class, 'acknowledge'])
        ->middleware('throttle:20,1')
        ->name('maintenance.reminders.acknowledge');
    Route::post('/maintenance/reminders/{occurrence}/dismiss', [MaintenanceReminderController::class, 'dismiss'])
        ->middleware('throttle:20,1')
        ->name('maintenance.reminders.dismiss');
    Route::get('/admin/source-diagnostics', SourceDiagnosticsController::class)->name('admin.source-diagnostics');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');
    Route::get('/profile/security', [ProfileSecurityController::class, 'show'])->name('profile.security.show');
    Route::post('/profile/security/sessions/end-others', [ProfileSecurityController::class, 'destroyOtherSessions'])
        ->middleware('throttle:5,1')
        ->name('profile.security.sessions.destroy-others');
    Route::get('/profile/sources', [SourceCredentialController::class, 'index'])->name('profile.sources.index');
    Route::put('/profile/sources', [SourceCredentialController::class, 'store'])->name('profile.sources.store');
    Route::post('/profile/sources/{providerKey}/test', [SourceCredentialController::class, 'test'])->name('profile.sources.test');
    Route::delete('/profile/sources/{providerKey}', [SourceCredentialController::class, 'destroy'])->name('profile.sources.destroy');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
});
