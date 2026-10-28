<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(DashboardService $service): JsonResponse
    {
        return response()->json($service->getDashboard());
    }

    public function machine(DashboardService $service, string $id): JsonResponse
    {
        $machine = $service->getMachinePerformance($id);

        if ($machine === null) {
            abort(404, 'Machine not found.');
        }

        return response()->json($machine);
    }
}
