-- Soal 3 — CTE + Window Function: performa dan ranking mesin
-- Asumsi:
--   - WO berstatus CANCELLED tidak dihitung.
--   - Achievement = SUM(good_qty) / SUM(target_qty) × 100, dibulatkan ke bilangan bulat.
--   - Ranking memakai RANK() atas achievement yang ditampilkan (dibulatkan):
--     nilai sama → ranking sama, ranking berikutnya dilompati (1, 2, 3, 3, 5).

WITH result_per_wo AS (
    SELECT
        wo_number,
        SUM(good_qty)   AS good_qty,
        SUM(reject_qty) AS reject_qty
    FROM production_result
    GROUP BY wo_number
),
machine_summary AS (
    SELECT
        m.machine_name,
        COALESCE(SUM(r.good_qty), 0)      AS total_good,
        COALESCE(SUM(r.reject_qty), 0)    AS total_reject,
        CASE
            WHEN SUM(w.target_qty) = 0 THEN 0
            ELSE ROUND(COALESCE(SUM(r.good_qty), 0) / SUM(w.target_qty) * 100)
        END                               AS achievement
    FROM work_order AS w
    JOIN machine    AS m ON m.machine_code = w.machine_code
    LEFT JOIN result_per_wo AS r ON r.wo_number = w.wo_number
    WHERE w.status <> 'CANCELLED'
    GROUP BY m.machine_code, m.machine_name
)
SELECT
    machine_name                                  AS `Machine`,
    total_good                                    AS `Total Good`,
    total_reject                                  AS `Total Reject`,
    achievement                                   AS `Achievement`,
    RANK() OVER (ORDER BY achievement DESC)       AS `Ranking`
FROM machine_summary
ORDER BY `Ranking` ASC, `Machine` ASC;
