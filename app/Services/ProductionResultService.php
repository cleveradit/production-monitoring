<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionResultService
{
    /**
     * Insert a production result for a RUNNING work order that has no result yet.
     * This is the only write operation of the application; the WO status is left unchanged.
     */
    public function store(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Lock the WO row so concurrent requests cannot both pass the "no result yet" check.
            $workOrder = DB::table('work_order')
                ->where('wo_number', $data['wo_number'])
                ->lockForUpdate()
                ->first(['wo_number', 'target_qty', 'status']);

            if ($workOrder->status !== 'RUNNING') {
                throw ValidationException::withMessages([
                    'wo_number' => "Production order must be RUNNING (current status: {$workOrder->status}).",
                ]);
            }

            if (DB::table('production_result')->where('wo_number', $workOrder->wo_number)->exists()) {
                throw ValidationException::withMessages([
                    'wo_number' => 'Production order already has a production result.',
                ]);
            }

            $actualStart = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $data['actual_start']);
            $actualFinish = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $data['actual_finish']);
            $goodQty = (int) $data['good_qty'];
            $targetQty = (int) $workOrder->target_qty;

            $row = [
                'wo_number' => $workOrder->wo_number,
                'actual_start' => $actualStart->format('Y-m-d H:i:s'),
                'actual_finish' => $actualFinish->format('Y-m-d H:i:s'),
                'runtime_minutes' => (int) $actualStart->diffInMinutes($actualFinish),
                'good_qty' => $goodQty,
                'reject_qty' => (int) $data['reject_qty'],
                'achievement' => $targetQty > 0 ? round($goodQty / $targetQty * 100, 2) : 0.0,
            ];

            $id = DB::table('production_result')->insertGetId($row);

            return ['id' => $id] + $row;
        });
    }
}
