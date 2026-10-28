<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a flat row from ProductionOrderService::paginate().
 */
class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'wo_number' => $this->wo_number,
            'product' => [
                'code' => $this->product_code,
                'name' => $this->product_name,
            ],
            'machine' => [
                'code' => $this->machine_code,
                'name' => $this->machine_name,
            ],
            'operator' => [
                'employee_no' => $this->employee_no,
                'name' => $this->full_name,
            ],
            'shift' => $this->shift,
            'target_qty' => (int) $this->target_qty,
            'plan_start' => $this->plan_start,
            'plan_finish' => $this->plan_finish,
            'status' => $this->status,
            'result' => $this->result_id === null ? null : [
                'actual_start' => $this->actual_start,
                'actual_finish' => $this->actual_finish,
                'good_qty' => (int) $this->good_qty,
                'reject_qty' => (int) $this->reject_qty,
                'achievement' => (float) $this->achievement,
            ],
        ];
    }
}
