# Implementation Plan: TICKET-002 (SQL Bagian A — Soal 1–4)

**Ticket:** `TICKET-002`  
**Status:** `REVIEW`  
**Target Audience:** AI Developer Agents  
**Depends On:** `TICKET-001`

---

## 1. Business Decision Snapshot (Approved)

| Item | Approved Value |
|---|---|
| Milestone | M2 — PRD §9 |
| Status WO di Soal 1 | Semua status (K1) |
| Status WO di Soal 2–4 | Mengecualikan `CANCELLED` (B1) |
| Rumus achievement | `SUM(good_qty) / SUM(target_qty) × 100`; target 0 → 0 (B2) |
| Pembulatan | `ROUND(x)` ke bilangan bulat di semua soal (B3) — lihat catatan di bawah |
| Urutan Soal 2 | Achievement presisi penuh DESC, lalu Product ASC |
| Ranking Soal 3 | `RANK() OVER (ORDER BY achievement_dibulatkan DESC)` (K3) |
| Tanggal acuan Soal 4 | `DATE(production_result.actual_start)` (B4); deret dari `MIN` s.d. `MAX` data |
| Baris GRAND TOTAL Soal 4 | Ditampilkan (K5) |
| `group_concat_max_len` | Tidak di-set: daftar nama terpanjang 496 byte (< default 1024) |
| `cte_max_recursion_depth` | Tidak di-set: rentang 364 hari (< default 1000) |

**Catatan konsekuensi B3 (sudah dicek ke data):** Soal 2 baris ke-2 dan ke-3 akan tampil `97` (No Drop Putih, 96,76) dan `96` (Giant Mortar, 95,89), sedangkan contoh di soal menulis `96` dan `95` (dipotong). Baris teratas tetap `97`. Ini sesuai PRD D12/B3 dan akan dijelaskan di README (M6).

**Temuan data lain:**

- Soal 1: dua produk teratas sama-sama punya 34 operator, sehingga urutan kedua (Total WO DESC) menentukan posisi.
- Soal 3: ada achievement dibulatkan yang sama (2 mesin di `94`), sehingga ranking berbentuk `1, 2, 3, 3, 5, …`.

---

## 2. Objective

Menyediakan empat file query SQL yang menjawab Soal 1–4 Bagian A, berjalan langsung di MySQL 8.4 database `manufacturing_test`, memenuhi kewajiban teknik per soal (CTE, window function, Recursive CTE + ROLLUP), dan lolos angka verifikasi PRD §7.1–7.4.

---

## 3. Non-Negotiable Technical Contract

Semua file: satu statement `SELECT`/`WITH`, read-only, header komentar singkat (soal + asumsi), alias kolom sesuai tabel output soal.

1. File: `sql/soal-1-many-to-many.sql`
   - Kolom: `Product`, `Total WO`, `Total Operator`, `Nama Operator`.
   - `Total Operator` = `COUNT(DISTINCT w.employee_no)`; `Nama Operator` = `GROUP_CONCAT(DISTINCT e.full_name ORDER BY e.full_name SEPARATOR ', ')`.
   - Join `work_order` → `product`, `employee`; tanpa filter status.
   - `ORDER BY` Total Operator DESC, Total WO DESC, Product ASC.
2. File: `sql/soal-2-cte-product-achievement.sql`
   - CTE `result_per_wo` (agregasi `production_result` per `wo_number`) → CTE `product_summary` (join `work_order` non-CANCELLED + `product`, `LEFT JOIN result_per_wo`) → `SELECT` akhir.
   - Kolom: `Product`, `Total WO`, `Target`, `Good Qty`, `Reject Qty`, `Achievement (%)`.
   - Tidak ada subquery di `FROM`/`WHERE`/`SELECT`.
3. File: `sql/soal-3-machine-ranking.sql`
   - CTE `result_per_wo` → CTE `machine_summary` (agregasi per mesin, achievement dibulatkan) → `SELECT` akhir dengan `RANK() OVER (ORDER BY achievement DESC)`.
   - Kolom: `Machine`, `Total Good`, `Total Reject`, `Achievement`, `Ranking`.
   - `ORDER BY` Ranking ASC, Machine ASC.
4. File: `sql/soal-4-recursive-trend.sql`
   - `WITH RECURSIVE`: CTE `date_bounds` (`DATE(MIN/MAX(actual_start))`) → CTE rekursif `date_series` (`+ INTERVAL 1 DAY` s.d. batas atas) → CTE `daily_production` (join `production_result` + `work_order` non-CANCELLED, group per `DATE(actual_start)`: good, reject, target) → `SELECT` dari `date_series LEFT JOIN daily_production`, `GROUP BY YEAR, MONTH, date WITH ROLLUP`.
   - Kolom: `level` (`DATE`/`MONTH`/`YEAR`/`GRAND TOTAL` dari `GROUPING()`), `year`, `month`, `date` (NULL pada subtotal), `total_good`, `total_reject`, `achievement`.
   - Nilai kosong → 0 (`COALESCE`); achievement 0 bila target 0.
   - `ORDER BY GROUPING(year), year, GROUPING(month), month, GROUPING(date), date` agar subtotal muncul setelah anak-anaknya dan GRAND TOTAL paling akhir.

---

## 4. Scope of Changes

### A. SQL

1. Tulis empat file di atas di folder `sql/`.

### B. Docs

1. `docs/features/sql-bagian-a.md` (feature doc: daftar file, cara menjalankan, asumsi) + registrasi di `docs/features/index.md`.
2. `docs/architecture.md`: hapus tanda *(rencana)* pada baris SQL Bagian A.
3. Hapus item M2 dari `docs/backlog.md`.

---

## 5. Acceptance Test Matrix

| Case | Input | Expected Result | Status |
|---|---|---|---|
| Soal 1 baris teratas | Jalankan file 1 | `Avitex Alkali Resisting Primer 20 Kg` — 55 / 34; jumlah baris = 43 | `[ ]` |
| Soal 2 baris teratas | Jalankan file 2 | `Avian Synthetic Enamel Black 5 Kg` — 38 / 87009 / 84416 / 679 / 97 | `[ ]` |
| Soal 2 tanpa subquery | Periksa teks file 2 | Tidak ada `(SELECT` | `[ ]` |
| Soal 3 tiga teratas | Jalankan file 3 | Labelling Machine 02 (176666/1572/97/1), Mixing Tank 01 (184727/1471/95/2), Paint Disperser 02 (164486/1402/94/3); 24 baris | `[ ]` |
| Boundary — ranking seri | File 3 | Dua mesin achievement `94` sama-sama ranking `3`, mesin berikutnya ranking `5` | `[ ]` |
| Soal 4 tanggal contoh | File 4, level `DATE` | 2026-01-02 → 5223/17/97; 2026-01-03 → 14866/111/100; 2026-01-04 → 6363/52/86 | `[ ]` |
| Boundary — tanggal kosong | File 4 | 2026-12-27 (dan 6 tanggal kosong lain di D7) muncul dengan 0/0/0 | `[ ]` |
| Kelengkapan deret | File 4 | 364 baris `DATE`, 12 `MONTH`, 1 `YEAR`, 1 `GRAND TOTAL` | `[ ]` |
| Failure — tanpa hardcode tanggal | Periksa teks file 4 | Tidak ada literal tanggal `'2026-…'` | `[ ]` |

---

## 6. Verification Commands

1. `docker compose exec -T db mysql -uapp -psecret manufacturing_test --table < sql/soal-1-many-to-many.sql`
2. Perintah yang sama untuk `soal-2`, `soal-3`, `soal-4`.
3. `grep -c "(SELECT" sql/soal-2-cte-product-achievement.sql` → `0`
4. `grep -cE "'[0-9]{4}-[0-9]{2}-[0-9]{2}" sql/soal-4-recursive-trend.sql` → `0`

Expected:

1. Semua query jalan tanpa error di MySQL 8.4 dengan `sql_mode` default (`ONLY_FULL_GROUP_BY`).
2. Semua angka di Acceptance Test Matrix cocok.

---

## 7. Out of Scope

1. Endpoint API yang memakai logika serupa (M3, M4).
2. Mengubah `SET SESSION` variabel MySQL atau konfigurasi server.
3. Menyesuaikan pembulatan dengan contoh soal (B3 sudah final).

---

## 8. Completion Checklist

- [x] Section 1 fully filled and status set correctly.
- [ ] All Non-Negotiable Technical Contract items implemented.
- [ ] Acceptance Test Matrix completed.
- [ ] Verification commands executed successfully.
- [ ] No out-of-scope changes introduced.
