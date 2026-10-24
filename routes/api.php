<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\BiometricController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GymLocationController;
use App\Http\Controllers\Admin\GymSettingsController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PaymentTypeController;
use App\Http\Controllers\Admin\PricingPlanController;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TerminalController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\SetupController;
use App\Http\Controllers\Central\SuperAdminController;
use App\Http\Controllers\Member\MemberPortalController;
use App\Http\Controllers\Webhook\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FitCloud.io Central API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public member portal read endpoints
    Route::get('/member/portal/info', [MemberPortalController::class, 'portalInfo'])
        ->middleware(['tenant', 'throttle:120,1']);

    // Public member authentication
    Route::post('/member/auth/login', [MemberPortalController::class, 'login'])
        ->middleware(['tenant', 'throttle:login']);
    Route::post('/member/auth/register', [MemberPortalController::class, 'register'])
        ->middleware(['tenant', 'throttle:login']);

    // Public leaderboard read endpoints
    Route::get('/campaigns', [CampaignController::class, 'leaderboard'])
        ->middleware(['tenant', 'throttle:120,1']);
    Route::get('/campaigns/active', [CampaignController::class, 'leaderboard'])
        ->middleware(['tenant', 'throttle:120,1']);
    Route::get('/campaigns/leaderboard', [CampaignController::class, 'leaderboard'])
        ->middleware(['tenant', 'throttle:120,1']);
    Route::get('/campaigns/active/leaderboard', [CampaignController::class, 'leaderboard'])
        ->middleware(['tenant', 'throttle:120,1']);

    // Public branding, settings and CMS read endpoints
    Route::get('/theme', [ThemeController::class, 'show'])->middleware(['tenant', 'throttle:120,1']);
    Route::get('/public/theme', [ThemeController::class, 'show'])->middleware(['tenant', 'throttle:120,1']);
    Route::get('/settings', [GymSettingsController::class, 'publicSettings'])->middleware(['tenant', 'throttle:120,1']);
    Route::get('/public/settings', [GymSettingsController::class, 'publicSettings'])->middleware(['tenant', 'throttle:120,1']);
    Route::get('/cms', [CmsController::class, 'index'])->middleware(['tenant', 'throttle:120,1']);
    Route::get('/public/cms', [CmsController::class, 'index'])->middleware(['tenant', 'throttle:120,1']);
    Route::get('/cms/{section}', [CmsController::class, 'show'])->middleware(['tenant', 'throttle:120,1']);

    // Central platform public authentication
    Route::post('/central/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:login');
    Route::post('/central/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    // Webhooks
    Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
        ->middleware('throttle:120,1');

    // Authenticated member portal endpoints
    Route::middleware(['auth:member', 'member', 'tenant'])->group(function () {
        Route::get('/member/profile', [MemberPortalController::class, 'profile']);
        Route::get('/member/active-session', [MemberPortalController::class, 'activeSession']);
        Route::post('/member/checkout', [MemberPortalController::class, 'checkout']);
        Route::post('/member/plans/purchase', [MemberPortalController::class, 'purchasePlan'])
            ->middleware('throttle:10,1');
    });

    // Authenticated central platform endpoints
    Route::middleware(['auth:sanctum', 'tenant_user'])->group(function () {
        Route::get('/central/auth/profile', [AuthController::class, 'profile']);
        Route::post('/central/auth/logout', [AuthController::class, 'logout']);

        // Setup may only be completed by the authenticated account that owns the license.
        Route::post('/setup/verify-license', [SetupController::class, 'verifyLicense'])
            ->middleware('throttle:10,1');
        Route::post('/setup/complete', [SetupController::class, 'completeSetup'])
            ->middleware('throttle:5,1');

        // Super Admin Endpoints
        Route::prefix('super-admin')->middleware('super_admin')->group(function () {
            Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
            Route::get('/tenants', [SuperAdminController::class, 'listTenants']);
            Route::post('/licenses/generate', [SuperAdminController::class, 'generateLicense']);
            Route::post('/licenses/renew', [SuperAdminController::class, 'renewLicense']);
            Route::patch('/tenants/{uuid}/status', [SuperAdminController::class, 'toggleTenantStatus']);
        });
    });

    // Gym Admin Center Endpoints (tenant scoped and pinned to the authenticated user)
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'tenant', 'tenant_user'])
        ->group(function () {
            Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

            // Member Management
            Route::get('/members', [MemberController::class, 'index']);
            Route::post('/members', [MemberController::class, 'store']);
            Route::get('/members/{identifier}', [MemberController::class, 'show']);
            Route::put('/members/{identifier}', [MemberController::class, 'update']);
            Route::delete('/members/{identifier}', [MemberController::class, 'destroy']);
            Route::post('/members/{identifier}/toggle-status', [MemberController::class, 'toggleStatus']);

            // Pricing Plans
            Route::get('/pricing-plans', [PricingPlanController::class, 'index']);
            Route::post('/pricing-plans', [PricingPlanController::class, 'store']);
            Route::put('/pricing-plans/{id}', [PricingPlanController::class, 'update']);
            Route::delete('/pricing-plans/{id}', [PricingPlanController::class, 'destroy']);

            // Subscriptions & Extensions & Renewals
            Route::get('/subscriptions', [SubscriptionController::class, 'index']);
            Route::post('/subscriptions', [SubscriptionController::class, 'store']);
            Route::post('/subscriptions/reconcile-statuses', [SubscriptionController::class, 'reconcileStatuses']);
            Route::put('/subscriptions/bulk-update', [SubscriptionController::class, 'bulkUpdate']);
            Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update']);
            Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);
            Route::post('/subscriptions/{id}/extend', [SubscriptionController::class, 'extend']);
            Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);
            Route::post('/subscriptions/{id}/unpause', [SubscriptionController::class, 'unpause']);
            Route::get('/renewals', [SubscriptionController::class, 'renewals']);

            // Transactions Ledger
            Route::get('/transactions', [TransactionController::class, 'index']);
            Route::post('/transactions', [TransactionController::class, 'store']);
            Route::patch('/transactions/{id}/status', [TransactionController::class, 'updateStatus']);
            Route::put('/transactions/{id}/amount', [TransactionController::class, 'updateAmount']);
            Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

            // Legacy Payments Route Aliases
            Route::get('/payments/transactions', [TransactionController::class, 'index']);
            Route::delete('/payments/transactions/{id}', [TransactionController::class, 'destroy']);
            Route::put('/payments/manual/{id}/amount', [TransactionController::class, 'updateAmount']);
            Route::post('/payments/manual/{id}/approve', [TransactionController::class, 'approve']);
            Route::post('/payments/manual/{id}/reject', [TransactionController::class, 'reject']);

            // Front-Desk Terminals & Access Security
            Route::get('/terminals', [TerminalController::class, 'index']);
            Route::get('/terminals/check-current', [TerminalController::class, 'checkCurrent']);
            Route::middleware('tenant_owner')->group(function () {
                Route::post('/terminals/enroll', [TerminalController::class, 'enroll']);
                Route::post('/terminals/toggle-lock', [TerminalController::class, 'toggleLock']);
                Route::delete('/terminals/{identifier}', [TerminalController::class, 'destroy']);
            });

            // Attendance & Kiosk & QR Locations
            Route::get('/check-ins', [CheckInController::class, 'index']);
            Route::get('/checkins/all', [CheckInController::class, 'index']);

            // Terminal Lock Protected Actions (Staff restricted to enrolled desk devices; Owners bypass)
            Route::middleware(['terminal'])->group(function () {
                Route::post('/check-ins', [CheckInController::class, 'checkIn']);
                Route::post('/checkins/simulate', [CheckInController::class, 'simulate']);
                Route::post('/checkins/simulate-fingerprint', [CheckInController::class, 'simulateFingerprint']);
                Route::post('/checkins/{identifier}/cancel', [CheckInController::class, 'cancelSession']);
                Route::post('/checkins/cancel-all-active', [CheckInController::class, 'cancelAllActive']);
                Route::delete('/checkins/clear-all', [CheckInController::class, 'clearAll']);
                Route::post('/staff-attendance/clock-in', [StaffAttendanceController::class, 'clockIn']);
                Route::post('/staff-attendance/{id}/clock-out', [StaffAttendanceController::class, 'clockOut']);
            });

            Route::get('/checkin/locations', [GymLocationController::class, 'index']);
            Route::get('/checkin/generate-link', [GymLocationController::class, 'generateLink']);
            Route::get('/locations', [GymLocationController::class, 'index']);
            Route::post('/locations', [GymLocationController::class, 'store']);
            Route::put('/locations/{identifier}', [GymLocationController::class, 'update']);
            Route::delete('/locations/{identifier}', [GymLocationController::class, 'destroy']);

            // Staff Attendance
            Route::get('/staff-attendance', [StaffAttendanceController::class, 'index']);
            Route::get('/staff/attendance', [StaffAttendanceController::class, 'index']);
            Route::get('/staff/attendance-qr', [StaffAttendanceController::class, 'generateQR']);
            Route::get('/staff-attendance/qr', [StaffAttendanceController::class, 'generateQR']);

            // Accounts & Payment Types
            Route::get('/accounts', [BankAccountController::class, 'index']);
            Route::get('/accounts/active', [BankAccountController::class, 'active']);
            Route::middleware('tenant_owner')->group(function () {
                Route::post('/accounts', [BankAccountController::class, 'store']);
                Route::put('/accounts/{identifier}', [BankAccountController::class, 'update']);
                Route::delete('/accounts/{identifier}', [BankAccountController::class, 'destroy']);

                Route::post('/payment-types', [PaymentTypeController::class, 'store']);
                Route::put('/payment-types/{identifier}', [PaymentTypeController::class, 'update']);
                Route::delete('/payment-types/{identifier}', [PaymentTypeController::class, 'destroy']);
            });
            Route::get('/payment-types', [PaymentTypeController::class, 'index']);

            Route::get('/accounts/summary', [AuditLogController::class, 'accountsSummary']);
            Route::get('/audit-logs', [AuditLogController::class, 'auditLogs']);
            Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

            // System Backup Endpoints (owner only)
            Route::middleware('tenant_owner')->group(function () {
                Route::get('/backups', [BackupController::class, 'index']);
                Route::post('/backups/create', [BackupController::class, 'createBackup']);
                Route::get('/backups/download/{id}', [BackupController::class, 'download']);
                Route::post('/backups/restore', [BackupController::class, 'restore']);
                Route::post('/backups/{id}/restore', [BackupController::class, 'restore']);
                Route::delete('/backups/{id}', [BackupController::class, 'destroy']);
            });

            // Staff directory is readable by staff; mutations are owner-only.
            Route::get('/staff', [StaffController::class, 'index']);
            Route::get('/permissions', [StaffController::class, 'listPermissions']);
            Route::middleware('tenant_owner')->group(function () {
                Route::post('/staff', [StaffController::class, 'store']);
                Route::put('/staff/{id}', [StaffController::class, 'update']);
                Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
                Route::post('/staff/{id}/reset-password', [StaffController::class, 'resetPassword']);
                Route::put('/staff/{id}/permissions', [StaffController::class, 'updatePermissions']);
                Route::post('/staff/bulk-permissions', [StaffController::class, 'bulkPermissions']);
                Route::patch('/staff/{id}/role', [StaffController::class, 'updateRole']);
            });

            // Settings, Theme & Hardware
            Route::get('/settings', [GymSettingsController::class, 'show']);
            Route::get('/theme', [ThemeController::class, 'show']);
            Route::middleware('tenant_owner')->group(function () {
                Route::post('/settings', [GymSettingsController::class, 'update']);
                Route::post('/theme', [ThemeController::class, 'update']);
            });

            // Leaderboard & Campaigns & Email Blast
            Route::get('/campaigns', [CampaignController::class, 'leaderboard']);
            Route::get('/campaigns/active', [CampaignController::class, 'leaderboard']);
            Route::get('/campaigns/leaderboard', [CampaignController::class, 'leaderboard']);
            Route::get('/campaigns/active/leaderboard', [CampaignController::class, 'leaderboard']);
            Route::get('/campaigns/logs', [CampaignController::class, 'getLogs']);
            Route::post('/campaigns', [CampaignController::class, 'updateCampaign']);
            Route::put('/campaigns/{id}', [CampaignController::class, 'updateCampaign']);
            Route::post('/campaigns/email-blast', [CampaignController::class, 'sendEmailBlast']);
            Route::post('/campaigns/send-email', [CampaignController::class, 'sendEmailBlast']);
            Route::post('/campaigns/logs/{uuid}/sync', [CampaignController::class, 'syncLog']);

            // CMS
            Route::get('/cms', [CmsController::class, 'index']);
            Route::get('/cms/{section}', [CmsController::class, 'show']);
            Route::middleware('tenant_owner')->group(function () {
                Route::post('/cms/upload', [CmsController::class, 'uploadMedia']);
                Route::post('/cms/{section}', [CmsController::class, 'updateSection']);
                Route::put('/cms/{section}', [CmsController::class, 'updateSection']);
            });

            // Biometrics & Fingerprint Scanner (Feature Gated)
            Route::middleware(['feature:biometric_access'])->group(function () {
                Route::post('/biometrics/register', [BiometricController::class, 'register']);
                Route::post('/biometrics/check-in', [BiometricController::class, 'checkIn']);
            });
        });
});
