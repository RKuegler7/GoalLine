<?php

namespace App\Http\Controllers;

use App\Services\HealthCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * For uptime monitors: 200 when everything works, 503 when anything is failing. No secrets, ever.
     */
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $result = $health->run();

        return response()->json($result, $result['status'] === 'ok' ? 200 : 503, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
