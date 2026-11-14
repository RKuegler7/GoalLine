<?php

namespace App\Http\Controllers;

use App\BuildStamp;
use App\Services\HealthCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * For uptime monitors: 200 when everything works, 503 when anything is failing, plus the build it's running (the
     * short commit hash and build time from deploy.zip; null when run straight from git). No secrets, ever.
     */
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $result = $health->run();

        return response()->json($result + ['build' => BuildStamp::current()], $result['status'] === 'ok' ? 200 : 503, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
