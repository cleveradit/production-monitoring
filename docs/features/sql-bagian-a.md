# SQL Bagian A (Soal 1–4)

**Status:** Live

## Summary

Empat query SQL read-only yang menjawab Soal 1–4 Bagian A technical test, dijalankan langsung di database `manufacturing_test` (MySQL 8.4). Setiap file berisi satu statement dan header komentar berisi asumsi. Logika status, rumus achievement, dan pembulatan mengikuti [PRD](../initiate-file/prd.md) B1–B4 dan K1, K3, K5.

## Quick Reference

| File | Soal | Teknik wajib | Kolom output | Baris |
|---|---|---|---|---:|
| `sql/soal-1-many-to-many.sql` | 1 — Product ↔ Operator | `COUNT(DISTINCT)`, `GROUP_CONCAT` | Product, Total WO, Total Operator, Nama Operator | 43 |
| `sql/soal-2-cte-product-achievement.sql` | 2 — Pencapaian per produk | CTE tanpa subquery | Product, Total WO, Target, Good Qty, Reject Qty, Achievement (%) | 43 |
| `sql/soal-3-machine-ranking.sql` | 3 — Ranking mesin | CTE → `RANK()` | Machine, Total Good, Total Reject, Achievement, Ranking | 24 |
| `sql/soal-4-recursive-trend.sql` | 4 — Trend Tahun → Bulan → Tanggal | `WITH RECURSIVE` + `WITH ROLLUP` + `GROUPING()` | level, year, month, date, total_good, total_reject, achievement | 378 |

Menjalankan satu file:

```bash
docker compose exec -T db mysql -uapp -psecret manufacturing_test --table < sql/soal-2-cte-product-achievement.sql
```

## Aturan per soal

| Soal | Status WO | Achievement | Urutan |
|---|---|---|---|
| 1 | Semua status | — | Total Operator DESC, Total WO DESC, Product ASC |
| 2 | Tanpa `CANCELLED` | `ROUND(SUM(good)/SUM(target)×100)` | Achievement presisi penuh DESC, Product ASC |
| 3 | Tanpa `CANCELLED` | Sama dengan Soal 2; `RANK()` atas nilai dibulatkan | Ranking ASC, Machine ASC |
| 4 | Tanpa `CANCELLED` | Per level (tanggal/bulan/tahun/grand total); 0 bila target 0 | Kronologis; subtotal setelah anak-anaknya |

Soal 4 `level`: `DATE` (364 baris, 2026-01-02 s.d. 2026-12-31), `MONTH` (12), `YEAR` (1), `GRAND TOTAL` (1).

## Gotchas

- Soal 2 memakai `ROUND()`, sehingga baris ke-2 dan ke-3 tampil `97` dan `96`, sedangkan contoh soal menulis `96` dan `95` (dipotong). Ini keputusan B3.
- Soal 3 punya nilai seri: Paint Disperser 02 dan QC Laboratory 01 sama-sama `94` → ranking `3`, mesin berikutnya ranking `5`.
- Soal 4 menghasilkan 9 tanggal bernilai 0/0/0. Tujuh di antaranya tanpa produksi (PRD D7). Dua lainnya (2026-01-05, 2026-08-19) punya hasil produksi, tetapi hanya dari WO `OPEN` dengan `good_qty` dan `reject_qty` 0 (PRD D3). Target pada tanggal tersebut tetap masuk ke subtotal bulan.
- Soal 4: baris `YEAR` dan `GRAND TOTAL` bernilai sama karena data hanya mencakup tahun 2026.
- Batas default MySQL cukup: daftar nama operator terpanjang 496 byte (`group_concat_max_len` 1024), rentang 364 hari (`cte_max_recursion_depth` 1000). Jika dataset bertambah melewati batas tersebut, set variabel sesi terkait.
- Hasil produksi selalu diagregasi per WO (`result_per_wo`) sebelum di-join, agar target tidak terhitung ganda bila suatu saat ada lebih dari satu hasil per WO.

## Related

- [PRD §7.1–7.4](../initiate-file/prd.md#71-soal-1--many-to-many-sql)
- [Data Model](../data-model.md)
