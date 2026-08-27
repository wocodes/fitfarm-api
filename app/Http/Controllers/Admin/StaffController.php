<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Display gym admin and staff users directory.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $staff = TenantUser::where('tenant_id', $tenantId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    /**
     * Create / Invite a new staff user.
     */
    public function store(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant_users,email',
            'password' => 'required|string|min:8',
            'role' => 'nullable|string|in:admin,receptionist,trainer',
        ]);

        $staff = TenantUser::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'receptionist',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff user created successfully.',
            'data' => $staff,
        ], 201);
    }

    /**
     * Update staff role.
     */
    public function updateRole(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $staff = TenantUser::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|string|in:owner,admin,receptionist,trainer',
        ]);

        $staff->update(['role' => $validated['role']]);

        return response()->json([
            'success' => true,
            'message' => "Staff role updated to {$validated['role']}.",
            'data' => $staff,
        ]);
    }
}
