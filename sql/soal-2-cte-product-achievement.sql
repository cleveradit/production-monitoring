-- Soal 2 — CTE Dasar: pencapaian produksi per Product
-- Asumsi:
--   - WO berstatus CANCELLED tidak dihitung (tidak punya hasil produksi tetapi punya target).
--   - Achievement = SUM(good_qty) / SUM(target_qty) × 100, dibulatkan ke bilangan bulat.
--   - Urutan memakai achievement presisi penuh, lalu nama produk.
--   - Hasil produksi diagregasi per WO lebih dulu agar target tidak terhitung ganda.

WITH result_per_wo AS (
    SELECT
        wo_number,
        SUM(good_qty)   AS good_qty,
        SUM(reject_qty) AS reject_qty
    FROM production_result
    GROUP BY wo_number
),
product_summary AS (
    SELECT
        p.product_name,
        COUNT(w.wo_number)                AS total_wo,
        SUM(w.target_qty)                 AS target_qty,
        COALESCE(SUM(r.good_qty), 0)      AS good_qty,
        COALESCE(SUM(r.reject_qty), 0)    AS reject_qty,
        CASE
            WHEN SUM(w.target_qty) = 0 THEN 0
            ELSE COALESCE(SUM(r.good_qty), 0) / SUM(w.target_qty) * 100
        END                               AS achievement
    FROM work_order AS w
    JOIN product    AS p ON p.product_code = w.product_code
    LEFT JOIN result_per_wo AS r ON r.wo_number = w.wo_number
    WHERE w.status <> 'CANCELLED'
    GROUP BY p.product_code, p.product_name
)
SELECT
    product_name         AS `Product`,
    total_wo             AS `Total WO`,
    target_qty           AS `Target`,
    good_qty             AS `Good Qty`,
    reject_qty           AS `Reject Qty`,
    ROUND(achievement)   AS `Achievement (%)`
FROM product_summary
ORDER BY achievement DESC, product_name ASC;
