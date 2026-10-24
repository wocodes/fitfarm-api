<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentTypeController extends Controller
{
    /**
     * Get all payment types
     */
    public function index(Request $request)
    {
        $types = DB::table('payment_types')
            ->where('tenant_id', app('tenant')->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($pt) {
                return [
                    'id' => $pt->id,
                    'uid' => $pt->uuid,
                    'uuid' => $pt->uuid,
                    'name' => $pt->name,
                    'code' => $pt->code,
                    'defaultAmount' => $pt->default_amount ? (float) $pt->default_amount : null,
                    'default_amount' => $pt->default_amount ? (float) $pt->default_amount : null,
                    'isActive' => (bool) $pt->is_active,
                    'is_active' => (bool) $pt->is_active,
                    'createdAt' => $pt->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Store new payment type
     */
    public function store(Request $request)
    {
        $name = $request->input('name');
        $code = $request->input('code') ?? Str::slug($name, '_');
        $defaultAmount = $request->input('defaultAmount') ?? $request->input('default_amount');
        $isActive = filter_var($request->input('isActive', $request->input('is_active', true)), FILTER_VALIDATE_BOOLEAN);

        if (! $name) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Payment type name is required.',
            ], 422);
        }

        $uuid = (string) Str::uuid();
        $now = now();

        $id = DB::table('payment_types')->insertGetId([
            'uuid' => $uuid,
            'tenant_id' => app('tenant')->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $code)),
            'default_amount' => $defaultAmount ? (float) $defaultAmount : null,
            'is_active' => $isActive,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Payment type created successfully.',
            'data' => [
                'id' => $id,
                'uid' => $uuid,
                'name' => $name,
                'code' => strtoupper(str_replace(' ', '_', $code)),
                'defaultAmount' => $defaultAmount ? (float) $defaultAmount : null,
                'isActive' => $isActive,
                'createdAt' => $now->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * Update payment type
     */
    public function update(Request $request, string $identifier)
    {
        $pt = DB::table('payment_types')
            ->where('tenant_id', app('tenant')->id)
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)->orWhere('id', $identifier);
            })
            ->first();

        if (! $pt) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Payment type not found.',
            ], 404);
        }

        $name = $request->input('name', $pt->name);
        $code = $request->input('code', $pt->code);
        $defaultAmount = $request->has('defaultAmount')
            ? $request->input('defaultAmount')
            : $request->input('default_amount', $pt->default_amount);
        $isActive = filter_var($request->input('isActive', $request->input('is_active', $pt->is_active)), FILTER_VALIDATE_BOOLEAN);

        DB::table('payment_types')
            ->where('id', $pt->id)
            ->update([
                'name' => $name,
                'code' => strtoupper(str_replace(' ', '_', $code)),
                'default_amount' => $defaultAmount ? (float) $defaultAmount : null,
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Payment type updated successfully.',
            'data' => [
                'id' => $pt->id,
                'uid' => $pt->uuid,
                'name' => $name,
                'code' => strtoupper(str_replace(' ', '_', $code)),
                'defaultAmount' => $defaultAmount ? (float) $defaultAmount : null,
                'isActive' => $isActive,
            ],
        ]);
    }

    /**
     * Delete payment type
     */
    public function destroy(string $identifier)
    {
        DB::table('payment_types')
            ->where('tenant_id', app('tenant')->id)
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)->orWhere('id', $identifier);
            })
            ->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Payment type deleted successfully.',
        ]);
    }
}
