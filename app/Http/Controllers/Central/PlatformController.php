<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingsService;

class PlatformController extends Controller
{
    public function show(PlatformSettingsService $settings)
    {
        return response()->json([
            'success' => true,
            'data' => $settings->publicPayload(),
        ]);
    }
}
