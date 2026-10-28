-- Soal 1 — Many-to-Many Query (Product ↔ Operator melalui work_order)
-- Asumsi:
--   - Total WO menghitung semua status WO (relasi operator–produk tetap ada walau WO batal).
--   - Operator dihitung satu kali per produk (COUNT DISTINCT).
--   - Urutan: Total Operator terbanyak, lalu Total WO terbanyak, lalu nama produk.

SELECT
    p.product_name                                                           AS `Product`,
    COUNT(w.wo_number)                                                       AS `Total WO`,
    COUNT(DISTINCT w.employee_no)                                            AS `Total Operator`,
    GROUP_CONCAT(DISTINCT e.full_name ORDER BY e.full_name SEPARATOR ', ')   AS `Nama Operator`
FROM work_order AS w
JOIN product    AS p ON p.product_code = w.product_code
JOIN employee   AS e ON e.employee_no  = w.employee_no
GROUP BY p.product_code, p.product_name
ORDER BY `Total Operator` DESC, `Total WO` DESC, `Product` ASC;
