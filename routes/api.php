<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BiometricController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GymSettingsController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PricingPlanController;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\SetupController;
use App\Http\Controllers\Central\SuperAdminController;
use App\Http\Controllers\Webhook\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FitCloud.io Central API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 1. Central Platform Public Endpoints
    Route::post('/central/auth/register', [AuthController::class, 'register']);
    Route::post('/central/auth/login', [AuthController::class, 'login']);

    // Setup Wizard Endpoints
    Route::post('/setup/verify-license', [SetupController::class, 'verifyLicense']);
    Route::post('/setup/complete', [SetupController::class, 'completeSetup']);

    // Webhooks
    Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle']);

    // 2. Central Platform Authenticated Endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/central/auth/profile', [AuthController::class, 'profile']);

        // Super Admin Endpoints (God Mode)
        Route::prefix('super-admin')->group(function () {
            Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
            Route::get('/tenants', [SuperAdminController::class, 'listTenants']);
            Route::post('/licenses/generate', [SuperAdminController::class, 'generateLicense']);
            Route::post('/licenses/renew', [SuperAdminController::class, 'renewLicense']);
            Route::patch('/tenants/{uuid}/status', [SuperAdminController::class, 'toggleTenantStatus']);
        });
    });

    // 3. Gym Admin Center Endpoints (Tenant Scoped via header/token)
    Route::prefix('admin')->middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Member Management
        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::get('/members/{identifier}', [MemberController::class, 'show']);

        // Pricing Plans
        Route::get('/pricing-plans', [PricingPlanController::class, 'index']);
        Route::post('/pricing-plans', [PricingPlanController::class, 'store']);
        Route::put('/pricing-plans/{id}', [PricingPlanController::class, 'update']);
        Route::delete('/pricing-plans/{id}', [PricingPlanController::class, 'destroy']);

        // Subscriptions & Extensions & Renewals
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::post('/subscriptions/{id}/extend', [SubscriptionController::class, 'extend']);
        Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);
        Route::post('/subscriptions/{id}/unpause', [SubscriptionController::class, 'unpause']);
        Route::get('/renewals', [SubscriptionController::class, 'renewals']);

        // Transactions Ledger
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::patch('/transactions/{id}/status', [TransactionController::class, 'updateStatus']);

        // Attendance & Kiosk
        Route::get('/check-ins', [CheckInController::class, 'index']);
        Route::post('/check-ins', [CheckInController::class, 'checkIn']);

        // Staff Attendance
        Route::get('/staff-attendance', [StaffAttendanceController::class, 'index']);
        Route::post('/staff-attendance/clock-in', [StaffAttendanceController::class, 'clockIn']);
        Route::post('/staff-attendance/{id}/clock-out', [StaffAttendanceController::class, 'clockOut']);

        // Accounts & Audit Trail
        Route::get('/accounts/summary', [AuditLogController::class, 'accountsSummary']);
        Route::get('/audit-logs', [AuditLogController::class, 'auditLogs']);

        // Staff Management
        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::patch('/staff/{id}/role', [StaffController::class, 'updateRole']);

        // Settings & Hardware
        Route::get('/settings', [GymSettingsController::class, 'show']);
        Route::post('/settings', [GymSettingsController::class, 'update']);

        // Leaderboard & Campaigns
        Route::get('/campaigns/leaderboard', [CampaignController::class, 'leaderboard']);
        Route::post('/campaigns', [CampaignController::class, 'updateCampaign']);

        // Theme & Branding Customization
        Route::get('/theme', [ThemeController::class, 'show']);
        Route::post('/theme', [ThemeController::class, 'update']);

        // Biometrics & Fingerprint Scanner (Feature Gated)
        Route::middleware(['feature:biometric_access'])->group(function () {
            Route::post('/biometrics/register', [BiometricController::class, 'register']);
            Route::post('/biometrics/check-in', [BiometricController::class, 'checkIn']);
        });
    });

});
