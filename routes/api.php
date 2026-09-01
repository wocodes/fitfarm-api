<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BiometricController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GymLocationController;
use App\Http\Controllers\Admin\GymSettingsController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PaymentTypeController;
use App\Http\Controllers\Admin\PricingPlanController;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriptionController;
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

    // System Permissions Reference Endpoint
    Route::get('/permissions', [StaffController::class, 'listPermissions']);

    // Member Portal Public Endpoints
    Route::get('/member/portal/info', [MemberPortalController::class, 'portalInfo']);
    Route::post('/member/auth/login', [MemberPortalController::class, 'login']);
    Route::post('/member/auth/register', [MemberPortalController::class, 'register']);

    // Public & Direct Leaderboard / Campaign Endpoints
    Route::get('/campaigns', [CampaignController::class, 'leaderboard']);
    Route::get('/campaigns/active', [CampaignController::class, 'leaderboard']);
    Route::get('/campaigns/leaderboard', [CampaignController::class, 'leaderboard']);
    Route::get('/campaigns/active/leaderboard', [CampaignController::class, 'leaderboard']);
    Route::post('/campaigns', [CampaignController::class, 'updateCampaign']);
    Route::put('/campaigns/{id}', [CampaignController::class, 'updateCampaign']);
    Route::post('/campaigns/email-blast', [CampaignController::class, 'sendEmailBlast']);
    Route::post('/campaigns/send-email', [CampaignController::class, 'sendEmailBlast']);
    // Public & Direct Audit Trail & System Backup Endpoints
    Route::get('/settings', [GymSettingsController::class, 'show']);
    Route::get('/public/settings', [GymSettingsController::class, 'publicSettings']);
    Route::post('/settings', [GymSettingsController::class, 'update']);
    Route::get('/audit-logs', [AuditLogController::class, 'auditLogs']);
    Route::get('/audit-logs/export', [AuditLogController::class, 'export']);
    Route::get('/backups', [BackupController::class, 'index']);
    Route::post('/backups/create', [BackupController::class, 'createBackup']);
    Route::get('/backups/download/{id}', [BackupController::class, 'download']);
    Route::post('/backups/restore', [BackupController::class, 'restore']);
    Route::post('/backups/{id}/restore', [BackupController::class, 'restore']);
    Route::delete('/backups/{id}', [BackupController::class, 'destroy']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/member/profile', [MemberPortalController::class, 'profile']);
    });

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
        Route::post('/central/auth/logout', [AuthController::class, 'logout']);

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
        Route::post('/payments/manual/{id}/approve', function (\Illuminate\Http\Request $req, $id) {
            $req->merge(['status' => 'approved']);
            return app(\App\Http\Controllers\Admin\TransactionController::class)->updateStatus($req, $id);
        });
        Route::post('/payments/manual/{id}/reject', function (\Illuminate\Http\Request $req, $id) {
            $req->merge(['status' => 'rejected']);
            return app(\App\Http\Controllers\Admin\TransactionController::class)->updateStatus($req, $id);
        });

        // Attendance & Kiosk & QR Locations
        Route::get('/check-ins', [CheckInController::class, 'index']);
        Route::get('/checkins/all', [CheckInController::class, 'index']);
        Route::get('/admin/checkins/all', [CheckInController::class, 'index']);
        Route::post('/check-ins', [CheckInController::class, 'checkIn']);
        Route::post('/checkins/simulate', [CheckInController::class, 'simulate']);
        Route::post('/admin/checkins/simulate', [CheckInController::class, 'simulate']);
        Route::post('/checkins/simulate-fingerprint', [CheckInController::class, 'simulateFingerprint']);
        Route::post('/admin/checkins/simulate-fingerprint', [CheckInController::class, 'simulateFingerprint']);
        Route::post('/check-in/identify-fingerprint', [CheckInController::class, 'simulateFingerprint']);
        Route::post('/admin/checkins/{identifier}/cancel', [CheckInController::class, 'cancelSession']);
        Route::post('/admin/checkins/cancel-all-active', [CheckInController::class, 'cancelAllActive']);
        Route::delete('/admin/checkins/clear-all', [CheckInController::class, 'clearAll']);
        
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
        Route::post('/staff-attendance/clock-in', [StaffAttendanceController::class, 'clockIn']);
        Route::post('/staff-attendance/{id}/clock-out', [StaffAttendanceController::class, 'clockOut']);

        // Accounts & Audit Trail
        Route::get('/accounts', [BankAccountController::class, 'index']);
        Route::get('/accounts/active', [BankAccountController::class, 'active']);
        Route::post('/accounts', [BankAccountController::class, 'store']);
        Route::put('/accounts/{identifier}', [BankAccountController::class, 'update']);
        Route::delete('/accounts/{identifier}', [BankAccountController::class, 'destroy']);

        // Payment Types
        Route::get('/payment-types', [PaymentTypeController::class, 'index']);
        Route::post('/payment-types', [PaymentTypeController::class, 'store']);
        Route::put('/payment-types/{identifier}', [PaymentTypeController::class, 'update']);
        Route::delete('/payment-types/{identifier}', [PaymentTypeController::class, 'destroy']);

        Route::get('/accounts/summary', [AuditLogController::class, 'accountsSummary']);
        Route::get('/audit-logs', [AuditLogController::class, 'auditLogs']);
        Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

        // System Backup Endpoints
        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups/create', [BackupController::class, 'createBackup']);
        Route::get('/backups/download/{id}', [BackupController::class, 'download']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
        Route::post('/backups/{id}/restore', [BackupController::class, 'restore']);
        Route::delete('/backups/{id}', [BackupController::class, 'destroy']);

        // Staff & Admin Management
        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/permissions', [StaffController::class, 'listPermissions']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::put('/staff/{id}', [StaffController::class, 'update']);
        Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
        Route::post('/staff/{id}/reset-password', [StaffController::class, 'resetPassword']);
        Route::put('/staff/{id}/permissions', [StaffController::class, 'updatePermissions']);
        Route::post('/staff/bulk-permissions', [StaffController::class, 'bulkPermissions']);
        Route::patch('/staff/{id}/role', [StaffController::class, 'updateRole']);

        // Settings & Hardware
        Route::get('/settings', [GymSettingsController::class, 'show']);
        Route::post('/settings', [GymSettingsController::class, 'update']);

        // Leaderboard & Campaigns & Email Blast
        Route::get('/campaigns', [CampaignController::class, 'leaderboard']);
        Route::get('/campaigns/active', [CampaignController::class, 'leaderboard']);
        Route::get('/campaigns/leaderboard', [CampaignController::class, 'leaderboard']);
        Route::get('/campaigns/active/leaderboard', [CampaignController::class, 'leaderboard']);
        Route::post('/campaigns', [CampaignController::class, 'updateCampaign']);
        Route::put('/campaigns/{id}', [CampaignController::class, 'updateCampaign']);
        Route::post('/campaigns/email-blast', [CampaignController::class, 'sendEmailBlast']);
        Route::post('/campaigns/send-email', [CampaignController::class, 'sendEmailBlast']);
        Route::get('/campaigns/logs', [CampaignController::class, 'getLogs']);
        Route::get('/super-admin/campaign-logs', [CampaignController::class, 'getLogs']);
        Route::post('/campaigns/logs/{uuid}/sync', [CampaignController::class, 'syncLog']);

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
