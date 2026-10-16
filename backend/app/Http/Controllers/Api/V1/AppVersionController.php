<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSupportedMobileApp;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    /**
     * The oldest mobile build that may still be used. Public and outside the
     * version check, so the app can ask at launch, before anyone logs in.
     */
    public function __invoke(): JsonResponse
    {
        return $this->success([
            'min_build' => EnsureSupportedMobileApp::minimumBuild(),
            'store_url' => EnsureSupportedMobileApp::storeUrl(),
        ]);
    }
}
