<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankAccountController extends Controller
{
    /**
     * Get all bank accounts
     */
    public function index(Request $request)
    {
        $accounts = DB::table('accounts')
            ->where('tenant_id', app('tenant')->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($acc) {
                return [
                    'id' => $acc->id,
                    'uid' => $acc->uuid,
                    'uuid' => $acc->uuid,
                    'bankName' => $acc->bank_name,
                    'bank_name' => $acc->bank_name,
                    'accountNumber' => $acc->account_number,
                    'account_number' => $acc->account_number,
                    'accountType' => $acc->account_type,
                    'account_type' => $acc->account_type,
                    'isActive' => (bool) $acc->is_active,
                    'is_active' => (bool) $acc->is_active,
                    'accountLabel' => "{$acc->bank_name} ({$acc->account_number})",
                    'createdAt' => $acc->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Get active bank accounts only
     */
    public function active(Request $request)
    {
        $accounts = DB::table('accounts')
            ->where('tenant_id', app('tenant')->id)
            ->where('is_active', true)
            ->orderBy('bank_name', 'asc')
            ->get()
            ->map(function ($acc) {
                return [
                    'id' => $acc->id,
                    'uid' => $acc->uuid,
                    'bankName' => $acc->bank_name,
                    'accountNumber' => $acc->account_number,
                    'accountType' => $acc->account_type,
                    'isActive' => true,
                    'accountLabel' => "{$acc->bank_name} ({$acc->account_number})",
                ];
            });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Store new bank account
     */
    public function store(Request $request)
    {
        $bankName = $request->input('bankName') ?? $request->input('bank_name');
        $accountNumber = $request->input('accountNumber') ?? $request->input('account_number');
        $accountType = $request->input('accountType') ?? $request->input('account_type', 'Current');
        $isActive = filter_var($request->input('isActive', $request->input('is_active', true)), FILTER_VALIDATE_BOOLEAN);

        if (! $bankName || ! $accountNumber) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Bank name and account number are required.',
            ], 422);
        }

        $uuid = (string) Str::uuid();
        $now = now();

        $id = DB::table('accounts')->insertGetId([
            'uuid' => $uuid,
            'tenant_id' => app('tenant')->id,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_type' => $accountType,
            'is_active' => $isActive,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newAccount = [
            'id' => $id,
            'uid' => $uuid,
            'uuid' => $uuid,
            'bankName' => $bankName,
            'bank_name' => $bankName,
            'accountNumber' => $accountNumber,
            'account_number' => $accountNumber,
            'accountType' => $accountType,
            'account_type' => $accountType,
            'isActive' => $isActive,
            'is_active' => $isActive,
            'accountLabel' => "{$bankName} ({$accountNumber})",
            'createdAt' => $now->toDateTimeString(),
        ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Bank account created successfully.',
            'data' => $newAccount,
        ], 201);
    }

    /**
     * Update existing bank account
     */
    public function update(Request $request, string $identifier)
    {
        $account = DB::table('accounts')
            ->where('tenant_id', app('tenant')->id)
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)->orWhere('id', $identifier);
            })
            ->first();

        if (! $account) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Bank account not found.',
            ], 404);
        }

        $bankName = $request->input('bankName') ?? $request->input('bank_name', $account->bank_name);
        $accountNumber = $request->input('accountNumber') ?? $request->input('account_number', $account->account_number);
        $accountType = $request->input('accountType') ?? $request->input('account_type', $account->account_type);
        $isActive = filter_var($request->input('isActive', $request->input('is_active', $account->is_active)), FILTER_VALIDATE_BOOLEAN);

        DB::table('accounts')
            ->where('id', $account->id)
            ->update([
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_type' => $accountType,
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Bank account updated successfully.',
            'data' => [
                'id' => $account->id,
                'uid' => $account->uuid,
                'bankName' => $bankName,
                'accountNumber' => $accountNumber,
                'accountType' => $accountType,
                'isActive' => $isActive,
                'accountLabel' => "{$bankName} ({$accountNumber})",
            ],
        ]);
    }

    /**
     * Delete bank account
     */
    public function destroy(string $identifier)
    {
        $deleted = DB::table('accounts')
            ->where('tenant_id', app('tenant')->id)
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)->orWhere('id', $identifier);
            })
            ->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Bank account deleted successfully.',
        ]);
    }
}
