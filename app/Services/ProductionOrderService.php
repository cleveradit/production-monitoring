<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    public const STATUSES = ['OPEN', 'RUNNING', 'FINISHED', 'CANCELLED'];

    /** Public sort key => qualified column. */
    public const SORTABLE_COLUMNS = [
        'wo_number' => 'w.wo_number',
        'plan_start' => 'w.plan_start',
        'plan_finish' => 'w.plan_finish',
        'target_qty' => 'w.target_qty',
        'status' => 'w.status',
        'product_name' => 'p.product_name',
        'machine_name' => 'm.machine_name',
    ];

    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public function paginate(array $filters): LengthAwarePaginator
    {
        // One result row per WO (the latest), so a WO never appears twice in the list.
        $latestResult = DB::table('production_result')
            ->selectRaw('wo_number, MAX(id) AS id')
            ->groupBy('wo_number');

        $query = DB::table('work_order AS w')
            ->join('product AS p', 'p.product_code', '=', 'w.product_code')
            ->join('machine AS m', 'm.machine_code', '=', 'w.machine_code')
            ->join('employee AS e', 'e.employee_no', '=', 'w.employee_no')
            ->leftJoinSub($latestResult, 'lr', 'lr.wo_number', '=', 'w.wo_number')
            ->leftJoin('production_result AS pr', 'pr.id', '=', 'lr.id')
            ->select([
                'w.wo_number', 'w.shift', 'w.target_qty', 'w.plan_start', 'w.plan_finish', 'w.status',
                'p.product_code', 'p.product_name',
                'm.machine_code', 'm.machine_name',
                'e.employee_no', 'e.full_name',
                'pr.id AS result_id', 'pr.actual_start', 'pr.actual_finish',
                'pr.good_qty', 'pr.reject_qty', 'pr.achievement',
            ]);

        $this->applyFilters($query, $filters);

        $sortColumn = self::SORTABLE_COLUMNS[$filters['sort_by'] ?? 'plan_start'];
        $sortDirection = $filters['sort_dir'] ?? 'desc';

        // wo_number as tie-breaker keeps page boundaries stable.
        $query->orderBy($sortColumn, $sortDirection);
        if ($sortColumn !== 'w.wo_number') {
            $query->orderBy('w.wo_number', $sortDirection);
        }

        return $query
            ->paginate((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';

            $query->where(function (Builder $q) use ($term) {
                $q->where('w.wo_number', 'like', $term)
                    ->orWhere('p.product_name', 'like', $term)
                    ->orWhere('m.machine_name', 'like', $term)
                    ->orWhere('e.full_name', 'like', $term);
            });
        }

        foreach (['product_code', 'machine_code', 'status'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where("w.{$field}", $filters[$field]);
            }
        }

        // Date range on DATE(plan_start), written as a range so the plan_start index is usable.
        if (filled($filters['date_from'] ?? null)) {
            $query->where('w.plan_start', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('w.plan_start', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->toDateString());
        }
    }
}
