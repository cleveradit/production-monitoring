<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductionResultRequest;
use App\Services\ProductionResultService;
use Illuminate\Http\JsonResponse;

class ProductionResultController extends Controller
{
    public function store(StoreProductionResultRequest $request, ProductionResultService $service): JsonResponse
    {
        return response()->json(['data' => $service->store($request->validated())], 201);
    }
}
