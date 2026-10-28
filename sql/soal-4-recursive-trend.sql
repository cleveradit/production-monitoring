-- Soal 4 — Recursive CTE: trend produksi Tahun → Bulan → Tanggal
-- Asumsi:
--   - Tanggal acuan = DATE(production_result.actual_start).
--   - Deret tanggal dibuat Recursive CTE dari tanggal produksi paling awal s.d. paling akhir
--     di data (tanpa tanggal hardcode / tabel kalender); tanggal tanpa produksi bernilai 0.
--   - WO berstatus CANCELLED tidak dihitung.
--   - Achievement = SUM(good_qty) / SUM(target_qty) × 100 pada level tersebut,
--     dibulatkan ke bilangan bulat; 0 jika target 0.
--   - level: DATE / MONTH / YEAR / GRAND TOTAL (dari GROUPING() hasil ROLLUP).

WITH RECURSIVE date_bounds AS (
    SELECT
        DATE(MIN(actual_start)) AS start_date,
        DATE(MAX(actual_start)) AS end_date
    FROM production_result
),
date_series AS (
    SELECT start_date AS production_date
    FROM date_bounds
    UNION ALL
    SELECT ds.production_date + INTERVAL 1 DAY
    FROM date_series AS ds
    JOIN date_bounds AS db ON ds.production_date < db.end_date
),
daily_production AS (
    SELECT
        DATE(pr.actual_start) AS production_date,
        SUM(pr.good_qty)      AS good_qty,
        SUM(pr.reject_qty)    AS reject_qty,
        SUM(w.target_qty)     AS target_qty
    FROM production_result AS pr
    JOIN work_order        AS w ON w.wo_number = pr.wo_number
    WHERE w.status <> 'CANCELLED'
    GROUP BY DATE(pr.actual_start)
),
daily_trend AS (
    SELECT
        YEAR(ds.production_date)        AS year,
        MONTH(ds.production_date)       AS month,
        ds.production_date              AS date,
        COALESCE(dp.good_qty, 0)        AS good_qty,
        COALESCE(dp.reject_qty, 0)      AS reject_qty,
        COALESCE(dp.target_qty, 0)      AS target_qty
    FROM date_series AS ds
    LEFT JOIN daily_production AS dp ON dp.production_date = ds.production_date
)
SELECT
    CASE
        WHEN GROUPING(year)  = 1 THEN 'GRAND TOTAL'
        WHEN GROUPING(month) = 1 THEN 'YEAR'
        WHEN GROUPING(date)  = 1 THEN 'MONTH'
        ELSE 'DATE'
    END                                   AS level,
    year,
    month,
    date,
    SUM(good_qty)                         AS total_good,
    SUM(reject_qty)                       AS total_reject,
    CASE
        WHEN SUM(target_qty) = 0 THEN 0
        ELSE ROUND(SUM(good_qty) / SUM(target_qty) * 100)
    END                                   AS achievement
FROM daily_trend
GROUP BY year, month, date WITH ROLLUP
ORDER BY GROUPING(year), year, GROUPING(month), month, GROUPING(date), date;
