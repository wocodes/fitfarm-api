<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * List members for current tenant
     */
    public function index(Request $request)
    {
        $query = Member::with(['activePlan.pricingPlan']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $members = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Create a new member
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'blood_group' => 'nullable|string',
            'genotype' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
        ]);

        $member = Member::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Member registered successfully.',
            'data' => $member,
        ], 201);
    }

    /**
     * Show member details by UUID or ID
     */
    public function show(string $identifier)
    {
        $member = Member::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->with(['plans.pricingPlan', 'checkIns' => function ($q) {
                $q->latest()->limit(10);
            }, 'transactions'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $member,
        ]);
    }
}
