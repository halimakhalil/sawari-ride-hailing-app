<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaController extends Controller
{
    /** Liveness + database readiness, for load balancers and uptime checks. */
    public function health(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable $e) {
            Log::error('health.database_unreachable', ['message' => $e->getMessage()]);
            $database = 'unreachable';
        }

        $healthy = $database === 'ok';

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => ['database' => $database],
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /** Known Lahore places, standing in for a geocoding / places API. */
    public function locations(): JsonResponse
    {
        return response()->json([
            'data' => config('rides.locations'),
            'meta' => ['service_area' => config('rides.service_area')],
        ]);
    }
}
