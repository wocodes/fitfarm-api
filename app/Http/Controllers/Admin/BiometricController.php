<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\BiometricService;
use Illuminate\Http\Request;

class BiometricController extends Controller
{
    protected BiometricService $biometricService;

    public function __construct(BiometricService $biometricService)
    {
        $this->biometricService = $biometricService;
    }

    /**
     * Register a member's fingerprint template
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'fingerprint_template' => 'required|string',
        ]);

        $member = Member::findOrFail($validated['member_id']);
        $updatedMember = $this->biometricService->registerFingerprint($member, $validated['fingerprint_template']);

        return response()->json([
            'success' => true,
            'message' => "Fingerprint registered successfully for {$updatedMember->formatted_display_name}.",
            'data' => $updatedMember,
        ]);
    }

    /**
     * High-speed fingerprint check-in scan
     */
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'fingerprint_template' => 'required|string',
        ]);

        $tenant = app('tenant');
        $result = $this->biometricService->verifyAndCheckIn($tenant, $validated['fingerprint_template']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json($result, 201);
    }
}
