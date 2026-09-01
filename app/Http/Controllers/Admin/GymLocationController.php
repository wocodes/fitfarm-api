<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GymLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GymLocationController extends Controller
{
    /**
     * List all gym locations
     */
    public function index(Request $request)
    {
        $locations = GymLocation::orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $locations,
        ]);
    }

    /**
     * Store new gym location
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1',
            'active' => 'nullable|boolean',
        ]);

        $tenantId = $request->get('tenant_id');

        $location = GymLocation::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'radius' => $validated['radius'] ?? 50,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Gym location created successfully.',
            'data' => $location,
        ], 201);
    }

    /**
     * Update gym location
     */
    public function update(Request $request, string $identifier)
    {
        $location = GymLocation::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
            'radius' => 'nullable|numeric|min:1',
            'active' => 'nullable|boolean',
        ]);

        $location->update($validated);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Gym location updated successfully.',
            'data' => $location,
        ]);
    }

    /**
     * Delete gym location
     */
    public function destroy(string $identifier)
    {
        $location = GymLocation::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        $location->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Gym location deleted.',
        ]);
    }

    /**
     * Generate QR Code Check-in Link
     */
    public function generateLink(Request $request)
    {
        $locationId = $request->input('locationId') ?? $request->input('location_id');
        $permanent = filter_var($request->input('permanent', true), FILTER_VALIDATE_BOOLEAN);

        $location = GymLocation::where('uuid', $locationId)
            ->orWhere('id', $locationId)
            ->first();

        $locationName = $location ? $location->name : 'Main Location';
        $locationUid = $location ? $location->uuid : ($locationId ?? (string) Str::uuid());

        $baseUrl = config('app.frontend_url', 'https://fitcloud.io');
        $timestamp = time();

        $qrLink = "{$baseUrl}/check-in?loc={$locationUid}&perm=" . ($permanent ? '1' : '0') . "&ts={$timestamp}";

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'qrLink' => $qrLink,
                'locationName' => $locationName,
                'timestamp' => $timestamp,
            ],
        ]);
    }
}
