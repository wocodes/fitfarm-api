<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    /**
     * Display gym admin and staff users directory.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $staff = TenantUser::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->latest()
            ->get()
            ->map(function ($u) use ($tenantId) {
                setPermissionsTeamId($u->tenant_id ?? $tenantId ?? 1);
                $u->permissions = $u->getAllPermissions()->pluck('name')->toArray();

                return $u;
            });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $staff,
        ]);
    }

    /**
     * Fetch master system roles and permissions from database.
     */
    public function listPermissions(Request $request)
    {
        $permissions = Permission::all()->map(function ($p) {
            return [
                'id' => $p->id,
                'key' => $p->name,
                'label' => $p->label ?? $p->name,
                'description' => $p->description ?? '',
                'category' => $p->category ?? 'General',
            ];
        });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Create / Register a new admin user.
     */
    public function store(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'fullName' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'username' => 'nullable|string',
            'phone' => 'nullable|string',
            'phoneNumber' => 'nullable|string',
            'gender' => 'nullable|string',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string',
            'roles' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $name = $validated['fullName'] ?? $validated['name'] ?? 'Admin User';
        $email = $validated['email'] ?? $validated['username'] ?? strtolower(str_replace(' ', '', $name)).'@gym.com';
        $phone = $validated['phone'] ?? $validated['phoneNumber'] ?? null;
        $role = ! empty($validated['roles']) ? $validated['roles'][0] : ($validated['role'] ?? 'admin');

        if (str_contains(strtolower((string) $role), 'super')) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Platform super-admin roles cannot be created inside a gym tenant.',
            ], 422);
        }

        $staff = TenantUser::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'gender' => $validated['gender'] ?? 'Male',
            'password' => Hash::make($validated['password']),
            'role' => $role,
        ]);

        setPermissionsTeamId($staff->tenant_id ?? $tenantId ?? 1);

        $initialPerms = $validated['permissions'] ?? ['MANAGE_MEMBERS', 'MANAGE_CHECKINS', 'VIEW_TRANSACTIONS'];
        $staff->syncPermissions($initialPerms);

        $roleObj = Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'sanctum',
            'tenant_id' => $staff->tenant_id ?? $tenantId ?? 1,
        ]);
        $staff->assignRole($roleObj);

        $staff->permissions = $staff->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Staff user created successfully.',
            'data' => $staff,
        ], 201);
    }

    /**
     * Update admin user details.
     */
    public function update(Request $request, $id)
    {
        $tenantId = $request->get('tenant_id');
        $staff = TenantUser::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(fn ($q) => $q->where('id', $id)->orWhere('email', $id))
            ->firstOrFail();

        $validated = $request->validate([
            'fullName' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'phoneNumber' => 'nullable|string',
            'phone' => 'nullable|string',
            'gender' => 'nullable|string',
            'role' => 'nullable|string',
        ]);

        $updateData = [];
        if (isset($validated['fullName']) || isset($validated['name'])) {
            $updateData['name'] = $validated['fullName'] ?? $validated['name'];
        }
        if (isset($validated['phoneNumber']) || isset($validated['phone'])) {
            $updateData['phone'] = $validated['phoneNumber'] ?? $validated['phone'];
        }
        if (isset($validated['gender'])) {
            $updateData['gender'] = $validated['gender'];
        }
        if (isset($validated['role'])) {
            if (str_contains(strtolower((string) $validated['role']), 'super')) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Platform super-admin roles cannot be assigned inside a gym tenant.',
                ], 422);
            }

            $updateData['role'] = $validated['role'];
            setPermissionsTeamId($staff->tenant_id ?? $tenantId ?? 1);
            $roleObj = Role::firstOrCreate([
                'name' => $validated['role'],
                'guard_name' => 'sanctum',
                'tenant_id' => $staff->tenant_id ?? $tenantId ?? 1,
            ]);
            $staff->syncRoles([$roleObj]);
        }

        $staff->update($updateData);

        setPermissionsTeamId($staff->tenant_id ?? $tenantId ?? 1);
        $staff->permissions = $staff->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Admin details updated successfully.',
            'data' => $staff,
        ]);
    }

    /**
     * Delete admin user.
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $request->get('tenant_id');
        $staff = TenantUser::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(fn ($q) => $q->where('id', $id)->orWhere('email', $id))
            ->firstOrFail();

        $staff->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Admin user deleted successfully.',
        ]);
    }

    /**
     * Reset password for admin user.
     */
    public function resetPassword(Request $request, $id)
    {
        $validated = $request->validate([
            'password' => 'nullable|string|min:6',
            'newPassword' => 'nullable|string|min:6',
        ]);

        $newPassword = $validated['newPassword'] ?? $validated['password'];

        $tenantId = $request->get('tenant_id');
        $staff = TenantUser::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(fn ($q) => $q->where('id', $id)->orWhere('email', $id)->orWhere('name', $id))
            ->firstOrFail();

        $staff->update([
            'password' => Hash::make($newPassword),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * Update an admin user's role.
     */
    public function updateRole(Request $request, $id)
    {
        $validated = $request->validate([
            'role' => 'required|string|max:50',
        ]);

        if (str_contains(strtolower($validated['role']), 'super')) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Platform super-admin roles cannot be assigned inside a gym tenant.',
            ], 422);
        }

        $tenantId = app('tenant')->id;

        $staff = TenantUser::where('tenant_id', $tenantId)
            ->whereKey($id)
            ->firstOrFail();

        $staff->update(['role' => $validated['role']]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Staff role updated successfully.',
            'data' => $staff,
        ]);
    }

    /**
     * Update admin user permissions.
     */
    public function updatePermissions(Request $request, $id)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
        ]);

        $tenantId = $request->get('tenant_id');
        $staff = TenantUser::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(fn ($q) => $q->where('id', $id)->orWhere('email', $id))
            ->firstOrFail();

        setPermissionsTeamId($staff->tenant_id ?? $tenantId ?? 1);
        $staff->syncPermissions($validated['permissions']);
        $staff->permissions = $staff->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Permissions updated successfully.',
            'data' => $staff,
        ]);
    }

    /**
     * Bulk assign default permissions to regular admins.
     */
    public function bulkPermissions(Request $request)
    {
        $tenantId = $request->get('tenant_id') ?? 1;

        $defaultPermissions = [
            'MANAGE_MEMBERS',
            'MANAGE_CHECKINS',
            'VIEW_TRANSACTIONS',
            'MANAGE_PRICING',
        ];

        $staffList = TenantUser::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('role', '!=', 'super_admin')
            ->where('role', '!=', 'ROLE_SUPER_ADMIN')
            ->get();

        foreach ($staffList as $staff) {
            setPermissionsTeamId($staff->tenant_id ?? $tenantId);
            $staff->syncPermissions($defaultPermissions);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Default permissions assigned to all regular admins.',
        ]);
    }
}
