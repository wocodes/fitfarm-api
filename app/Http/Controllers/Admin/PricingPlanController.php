<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use Illuminate\Http\Request;

class PricingPlanController extends Controller
{
    /**
     * Display a listing of gym pricing plans.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $plans = PricingPlan::where('tenant_id', $tenantId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Store a newly created pricing plan.
     */
    public function store(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $plan = PricingPlan::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'duration_days' => $validated['duration_days'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Membership pricing plan created successfully.',
            'data' => $plan,
        ], 201);
    }

    /**
     * Update the specified pricing plan.
     */
    public function update(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $plan = PricingPlan::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'duration_days' => 'sometimes|required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Membership pricing plan updated successfully.',
            'data' => $plan,
        ]);
    }

    /**
     * Toggle or delete pricing plan.
     */
    public function destroy(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $plan = PricingPlan::where('tenant_id', $tenantId)->findOrFail($id);

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Membership pricing plan removed successfully.',
        ]);
    }
}
