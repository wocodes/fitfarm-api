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
        $query = Member::with(['activePaidPlans.pricingPlan', 'activePaidPlan.pricingPlan', 'latestPaidPlan.pricingPlan', 'registrationPlan.pricingPlan']);

        if ($search = trim($request->input('search') ?? '')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        if ($gender = $request->input('gender')) {
            $query->where('gender', strtolower($gender));
        }

        if ($status = strtoupper(trim($request->input('status') ?? ''))) {
            if ($status === 'PAUSED') {
                $query->whereHas('activePaidPlans', function ($q) {
                    $q->where('status', 'paused');
                });
            } else if ($status === 'EXPIRED') {
                $query->where(function ($q) {
                    $q->whereHas('latestPaidPlan', function ($lq) {
                        $lq->where('status', 'expired');
                    })->orWhereHas('registrationPlan', function ($rq) {
                        $rq->where('status', 'expired');
                    });
                });
            } else if ($status === 'NOT_YET_REGISTERED') {
                $query->whereDoesntHave('plans');
            } else if ($status === 'BIOMETRICS' || $status === 'REGISTERED_BIOMETRICS') {
                $query->whereNotNull('biometric_hash')->where('biometric_hash', '!=', '');
            } else if ($status === 'NO_CHECKINS_7_DAYS') {
                $query->whereDoesntHave('checkIns', function ($q) {
                    $q->where('created_at', '>=', now()->subDays(7));
                });
            } else if ($status === 'REGISTERED' || $status === 'ACTIVE') {
                $query->whereHas('plans');
            }
        }

        $perPage = $request->input('per_page', 50);
        $query->orderBy('updated_at', 'desc')->orderBy('id', 'desc');

        $formatMemberData = function ($member) {
            $planName = null;
            $regStatus = 'NOT_YET_REGISTERED';
            $regExpiry = null;

            // 1. Workout Subscription Plan(s) (excluding System/Registration fee plans, concatenating if multiple active/paused)
            if ($member->activePaidPlans->isNotEmpty()) {
                $planNames = $member->activePaidPlans->map(function ($plan) {
                    if (!$plan->pricingPlan) return null;
                    $name = $plan->pricingPlan->formatted_name;
                    if ($plan->status === 'paused') {
                        $name .= ' (⏸)';
                    }
                    return $name;
                })->filter()->unique()->implode(', ');

                $planName = $planNames ?: null;
            } else if ($member->latestPaidPlan && $member->latestPaidPlan->pricingPlan) {
                $planName = $member->latestPaidPlan->pricingPlan->formatted_name . ' - Expired';
            }

            // 2. Registration Status & Expiration
            if ($member->registrationPlan && $member->registrationPlan->pricingPlan) {
                $expiresAt = $member->registrationPlan->expires_at;
                if ($expiresAt && \Illuminate\Support\Carbon::parse($expiresAt)->isPast()) {
                    $regStatus = 'EXPIRED';
                } else {
                    $regStatus = 'REGISTERED';
                }
                $regExpiry = $expiresAt;
            } else if ($member->activePaidPlan || $member->latestPaidPlan) {
                $regStatus = 'REGISTERED';
                $regExpiry = $member->activePaidPlan ? $member->activePaidPlan->expires_at : ($member->latestPaidPlan ? $member->latestPaidPlan->expires_at : null);
            }

            $mArr = $member->toArray();
            $mArr['computed_plan_name'] = $planName;
            $mArr['registration_source'] = $member->registration_source ?: 'Self (Web)';
            $mArr['registration_status'] = $regStatus;
            $mArr['registration_expiry_date'] = $regExpiry;
            return $mArr;
        };

        if ($perPage === 'all' || $perPage > 500) {
            $members = $query->get()->map($formatMemberData);
            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
        }

        $paginated = $query->paginate((int)$perPage);
        $paginated->getCollection()->transform($formatMemberData);

        return response()->json([
            'success' => true,
            'data' => $paginated,
        ]);
    }

    /**
     * Helper to map incoming camelCase or snake_case payload into DB field array
     */
    private function mapMemberPayload(Request $request): array
    {
        $input = $request->all();
        
        $fields = [
            'first_name' => $input['first_name'] ?? $input['firstName'] ?? null,
            'last_name' => $input['last_name'] ?? $input['lastName'] ?? null,
            'email' => $input['email'] ?? null,
            'phone' => $input['phone'] ?? $input['phoneNumber'] ?? null,
            'gender' => isset($input['gender']) ? strtolower($input['gender']) : null,
            'date_of_birth' => $input['date_of_birth'] ?? $input['dob'] ?? null,
            'address' => $input['address'] ?? null,
            'blood_group' => $input['blood_group'] ?? $input['bloodGroup'] ?? null,
            'genotype' => $input['genotype'] ?? null,
            'emergency_contact_name' => $input['emergency_contact_name'] ?? $input['emergencyContactName'] ?? ($input['emergencyContact']['contactName'] ?? null),
            'emergency_contact_phone' => $input['emergency_contact_phone'] ?? $input['emergencyContactPhone'] ?? ($input['emergencyContact']['contactPhoneNumber'] ?? null),
            'emergency_contact_relationship' => $input['emergency_contact_relationship'] ?? $input['emergencyContactRelationship'] ?? ($input['emergencyContact']['relationship'] ?? null),
            'occupation' => $input['occupation'] ?? null,
            'nin' => $input['nin'] ?? null,
            'blood_pressure' => $input['blood_pressure'] ?? $input['bloodPressure'] ?? null,
            'medical_conditions' => $input['medical_conditions'] ?? $input['medicalConditions'] ?? null,
            'other_ailments' => $input['other_ailments'] ?? $input['otherAilments'] ?? null,
            'hmo_provider' => $input['hmo_provider'] ?? $input['hmoProvider'] ?? null,
            'hmo_number' => $input['hmo_number'] ?? $input['hmoNumber'] ?? null,
            'next_of_kin_name' => $input['next_of_kin_name'] ?? $input['nextOfKinName'] ?? ($input['nextOfKin']['nokName'] ?? null),
            'next_of_kin_phone' => $input['next_of_kin_phone'] ?? $input['nextOfKinPhone'] ?? ($input['nextOfKin']['nokPhone'] ?? null),
            'next_of_kin_relationship' => $input['next_of_kin_relationship'] ?? $input['nextOfKinRelationship'] ?? ($input['nextOfKin']['nokRelationship'] ?? null),
            'next_of_kin_address' => $input['next_of_kin_address'] ?? $input['nextOfKinAddress'] ?? ($input['nextOfKin']['nokAddress'] ?? null),
        ];

        // Filter out null values if updating or creating
        return array_filter($fields, fn($val) => !is_null($val));
    }

    /**
     * Create a new member
     */
    public function store(Request $request)
    {
        $payload = $this->mapMemberPayload($request);

        $validated = validator($payload, [
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
            'emergency_contact_relationship' => 'nullable|string',
            'occupation' => 'nullable|string',
            'nin' => 'nullable|string',
            'blood_pressure' => 'nullable|string',
            'medical_conditions' => 'nullable|string',
            'other_ailments' => 'nullable|string',
            'hmo_provider' => 'nullable|string',
            'hmo_number' => 'nullable|string',
            'next_of_kin_name' => 'nullable|string',
            'next_of_kin_phone' => 'nullable|string',
            'next_of_kin_relationship' => 'nullable|string',
            'next_of_kin_address' => 'nullable|string',
        ])->validate();

        $user = $request->user();
        $adminName = 'Admin';
        if ($user) {
            $adminName = $user->name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        }
        if (empty($adminName)) {
            $adminName = 'Kelechi Sunday';
        }

        $validated['registration_source'] = "Admin (" . $adminName . ")";
        $validated['registration_status'] = 'REGISTERED';

        $member = Member::create($validated);

        // Generate password if requested or present
        $words = ["Swift", "Eagle", "Mountain", "River", "Golden", "Lion", "Azure", "Flame", "Silent", "Shadow", "Royal", "Noble", "Strong", "Fit", "Bold"];
        $w1 = $words[array_rand($words)];
        $w2 = $words[array_rand($words)];
        $num = rand(10, 99);
        $tempPassword = $request->input('password') ?? "{$w1}{$w2}{$num}";

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Member registered successfully.',
            'data' => array_merge($member->toArray(), [
                'memberUid' => $member->uuid,
                'tempPassword' => $tempPassword,
            ]),
        ], 201);
    }

    /**
     * Update an existing member
     */
    public function update(Request $request, string $identifier)
    {
        $member = Member::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        $payload = $this->mapMemberPayload($request);

        $validated = validator($payload, [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,other',
            'status' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'blood_group' => 'nullable|string',
            'genotype' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
            'emergency_contact_relationship' => 'nullable|string',
            'occupation' => 'nullable|string',
            'nin' => 'nullable|string',
            'blood_pressure' => 'nullable|string',
            'medical_conditions' => 'nullable|string',
            'other_ailments' => 'nullable|string',
            'hmo_provider' => 'nullable|string',
            'hmo_number' => 'nullable|string',
            'next_of_kin_name' => 'nullable|string',
            'next_of_kin_phone' => 'nullable|string',
            'next_of_kin_relationship' => 'nullable|string',
            'next_of_kin_address' => 'nullable|string',
        ])->validate();

        $member->update($validated);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Member details updated successfully.',
            'data' => $member,
        ]);
    }

    /**
     * Delete a member
     */
    public function destroy(string $identifier)
    {
        $member = Member::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member deleted successfully.',
        ]);
    }

    /**
     * Toggle member active/inactive status
     */
    public function toggleStatus(string $identifier)
    {
        $member = Member::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        $member->status = strtolower($member->status) === 'active' ? 'inactive' : 'active';
        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Member status updated.',
            'data' => $member,
        ]);
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
