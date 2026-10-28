<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const STATUSES = ['RUNNING', 'FINISHED', 'OPEN', 'CANCELLED'];

    private const TOP_MACHINE_LIMIT = 10;

    private const TREND_DAYS = 7;

    public function getDashboard(): array
    {
        // "Today" is the latest production date in the data, not the server date (R6).
        $dataDate = DB::scalar('SELECT DATE(MAX(actual_start)) FROM production_result');

        return [
            'summary' => $this->summary($dataDate),
            'trend_7_days' => $dataDate === null ? [] : $this->trend($dataDate),
            'status_breakdown' => $this->statusBreakdown(),
            'top_machines' => $this->topMachines(),
        ];
    }

    public function getMachinePerformance(string $machineCode): ?array
    {
        // Results and downtime are aggregated per WO in separate CTEs so that
        // multiple downtime rows never duplicate good/reject quantities.
        $row = DB::selectOne(<<<'SQL'
            WITH result_per_wo AS (
                SELECT wo_number, SUM(good_qty) AS good_qty, SUM(reject_qty) AS reject_qty
                FROM production_result
                GROUP BY wo_number
            ),
            downtime_per_wo AS (
                SELECT wo_number, SUM(duration_minutes) AS duration_minutes
                FROM downtime
                GROUP BY wo_number
            )
            SELECT
                m.machine_code,
                m.machine_name,
                COUNT(w.wo_number) AS total_order,
                COALESCE(SUM(CASE WHEN w.status <> 'CANCELLED' THEN r.good_qty END), 0) AS good_qty,
                COALESCE(SUM(CASE WHEN w.status <> 'CANCELLED' THEN r.reject_qty END), 0) AS reject_qty,
                COALESCE(SUM(CASE WHEN w.status <> 'CANCELLED' THEN w.target_qty END), 0) AS target_qty,
                COALESCE(SUM(d.duration_minutes), 0) AS downtime_minutes
            FROM machine AS m
            LEFT JOIN work_order AS w ON w.machine_code = m.machine_code
            LEFT JOIN result_per_wo AS r ON r.wo_number = w.wo_number
            LEFT JOIN downtime_per_wo AS d ON d.wo_number = w.wo_number
            WHERE m.machine_code = ?
            GROUP BY m.machine_code, m.machine_name
            SQL, [$machineCode]);

        if ($row === null) {
            return null;
        }

        return [
            'machine_code' => $row->machine_code,
            'machine_name' => $row->machine_name,
            'total_order' => (int) $row->total_order,
            'good_qty' => (int) $row->good_qty,
            'reject_qty' => (int) $row->reject_qty,
            'downtime_minutes' => (int) $row->downtime_minutes,
            'achievement' => $this->achievement((int) $row->good_qty, (int) $row->target_qty),
        ];
    }

    private function summary(?string $dataDate): array
    {
        $orders = DB::selectOne(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM machine) AS total_machine,
                COALESCE(SUM(status = 'RUNNING'), 0) AS running_order,
                COALESCE(SUM(status = 'FINISHED'), 0) AS finished_order
            FROM work_order
            SQL);

        $today = DB::selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(w.target_qty), 0) AS target_qty,
                COALESCE(SUM(pr.good_qty), 0) AS good_qty,
                COALESCE(SUM(pr.reject_qty), 0) AS reject_qty
            FROM production_result AS pr
            JOIN work_order AS w ON w.wo_number = pr.wo_number
            WHERE w.status <> 'CANCELLED'
              AND pr.actual_start >= ?
              AND pr.actual_start < ? + INTERVAL 1 DAY
            SQL, [$dataDate, $dataDate]);

        return [
            'total_machine' => (int) $orders->total_machine,
            'running_order' => (int) $orders->running_order,
            'finished_order' => (int) $orders->finished_order,
            'today_target' => (int) $today->target_qty,
            'today_good' => (int) $today->good_qty,
            'today_reject' => (int) $today->reject_qty,
            'achievement' => $this->achievement((int) $today->good_qty, (int) $today->target_qty),
            'data_date' => $dataDate,
        ];
    }

    private function trend(string $dataDate): array
    {
        $endDate = CarbonImmutable::parse($dataDate);
        $startDate = $endDate->subDays(self::TREND_DAYS - 1);

        $rows = collect(DB::select(<<<'SQL'
            SELECT
                DATE(pr.actual_start) AS production_date,
                SUM(pr.good_qty) AS good_qty,
                SUM(pr.reject_qty) AS reject_qty,
                SUM(w.target_qty) AS target_qty
            FROM production_result AS pr
            JOIN work_order AS w ON w.wo_number = pr.wo_number
            WHERE w.status <> 'CANCELLED'
              AND pr.actual_start >= ?
              AND pr.actual_start < ? + INTERVAL 1 DAY
            GROUP BY DATE(pr.actual_start)
            SQL, [$startDate->toDateString(), $endDate->toDateString()]))
            ->keyBy('production_date');

        // Every date in the window is returned, dates without production as 0.
        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(function ($date) use ($rows) {
                $row = $rows->get($date->toDateString());
                $good = (int) ($row->good_qty ?? 0);

                return [
                    'date' => $date->toDateString(),
                    'good_qty' => $good,
                    'reject_qty' => (int) ($row->reject_qty ?? 0),
                    'achievement' => $this->achievement($good, (int) ($row->target_qty ?? 0)),
                ];
            })
            ->values()
            ->all();
    }

    private function statusBreakdown(): array
    {
        $totals = collect(DB::select(
            'SELECT status, COUNT(*) AS total FROM work_order GROUP BY status'
        ))->pluck('total', 'status');

        return array_map(fn (string $status) => [
            'status' => $status,
            'total' => (int) ($totals[$status] ?? 0),
        ], self::STATUSES);
    }

    private function topMachines(): array
    {
        $rows = DB::select(<<<'SQL'
            WITH result_per_wo AS (
                SELECT wo_number, SUM(good_qty) AS good_qty
                FROM production_result
                GROUP BY wo_number
            )
            SELECT
                m.machine_code,
                m.machine_name,
                COALESCE(SUM(r.good_qty), 0) AS good_qty,
                SUM(w.target_qty) AS target_qty
            FROM work_order AS w
            JOIN machine AS m ON m.machine_code = w.machine_code
            LEFT JOIN result_per_wo AS r ON r.wo_number = w.wo_number
            WHERE w.status <> 'CANCELLED'
            GROUP BY m.machine_code, m.machine_name
            ORDER BY good_qty DESC, m.machine_code ASC
            LIMIT ?
            SQL, [self::TOP_MACHINE_LIMIT]);

        return array_map(fn (object $row) => [
            'machine_code' => $row->machine_code,
            'machine_name' => $row->machine_name,
            'good_qty' => (int) $row->good_qty,
            'achievement' => $this->achievement((int) $row->good_qty, (int) $row->target_qty),
        ], $rows);
    }

    private function achievement(int $good, int $target): float
    {
        return $target > 0 ? round($good / $target * 100, 2) : 0.0;
    }
}
