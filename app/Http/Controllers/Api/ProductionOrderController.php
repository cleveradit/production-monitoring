<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductionOrderRequest;
use App\Http\Resources\ProductionOrderResource;
use App\Services\ProductionOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductionOrderController extends Controller
{
    public function index(IndexProductionOrderRequest $request, ProductionOrderService $service): AnonymousResourceCollection
    {
        return ProductionOrderResource::collection($service->paginate($request->validated()));
    }
}
