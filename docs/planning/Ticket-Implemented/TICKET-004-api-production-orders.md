# Implementation Plan: TICKET-004 (API Production Orders & Production Results)

**Ticket:** `TICKET-004`  
**Status:** `DONE`  
**Target Audience:** AI Developer Agents  
**Depends On:** `TICKET-003` (routing API + render JSON di `bootstrap/app.php`)

---

## 1. Business Decision Snapshot (Approved)

| Item | Approved Value |
|---|---|
| Milestone | M4 — PRD §9 |
| Endpoint | `GET /api/production-orders`, `POST /api/production-results` (PRD §7.5.3–7.5.4) |
| Akses data | Query Builder (`DB::table`) di service, konsisten dengan `DashboardService`; tidak membuat Eloquent model |
| Bentuk response list | `ProductionOrderResource::collection($paginator)` → `data`, `links`, `meta`; query string filter dipertahankan di link (`withQueryString()`) |
| `search` | `LIKE %term%` pada `wo_number`, `product_name`, `machine_name`, `employee.full_name` |
| `product_code`, `machine_code` | Filter persis; kode yang tidak ada → list kosong (bukan 422) |
| `status` | `in:OPEN,RUNNING,FINISHED,CANCELLED` |
| `date_from`, `date_to` | `date_format:Y-m-d`; filter `plan_start >= date_from` dan `plan_start < date_to + 1 hari` (setara `DATE(plan_start)`); `date_to` `after_or_equal:date_from` |
| Sorting | `sort_by` whitelist: `wo_number`, `plan_start`, `plan_finish`, `target_qty`, `status`, `product_name`, `machine_name`; default `plan_start`. `sort_dir` `asc`/`desc`, default `desc`. Sort sekunder `wo_number` (arah sama) agar pagination stabil |
| `per_page` | `integer`, `1–100`, default 15 |
| `result` per WO | Diambil dari baris `production_result` dengan `id` terbesar per WO (join ke subquery `MAX(id)`), sehingga satu WO selalu satu baris walau suatu saat ada duplikat; `null` jika belum ada hasil |
| Validasi format POST | FormRequest, error standar Laravel 422 |
| Aturan bisnis POST | Di service dalam `DB::transaction` dengan `lockForUpdate()` pada baris `work_order` (mencegah dua request bersamaan sama-sama lolos cek "belum ada hasil"); pelanggaran dilempar `ValidationException` pada field `wo_number` → format 422 yang sama |
| `actual_start`/`actual_finish` "≤ sekarang" | `before_or_equal:now` dengan timezone aplikasi `Asia/Jakarta` |
| Field input qty | `good_qty`, `reject_qty` (B8) |
| Nilai dihitung server | `runtime_minutes` = selisih menit utuh; `achievement` = `ROUND(good_qty / target_qty × 100, 2)`, target 0 → 0 |
| Status WO setelah POST | Tidak diubah (R3) |
| Uji jalur sukses POST | Dengan data awal tidak ada WO yang bisa menerima POST (D5). Jalur sukses diuji lewat `tinker` di dalam transaksi yang **di-rollback**: insert `work_order` sementara berstatus RUNNING → panggil service → cek hasil → rollback. Tidak ada perubahan data yang tersimpan (R1) |

---

## 2. Objective

Menyediakan endpoint list production order dengan search, filter, sort, dan pagination untuk kebutuhan operasional, serta satu-satunya endpoint tulis untuk mencatat hasil produksi dengan validasi format dan aturan bisnis yang mencegah data tidak valid atau ganda.

---

## 3. Non-Negotiable Technical Contract

1. File: `routes/api.php`
   - `Route::get('/production-orders', [ProductionOrderController::class, 'index'])`
   - `Route::post('/production-results', [ProductionResultController::class, 'store'])`
2. File: `app/Http/Requests/IndexProductionOrderRequest.php`
   - Rules: `search` `nullable|string|max:100`; `product_code` `nullable|string|max:20`; `machine_code` `nullable|string|max:10`; `status` `nullable|in:OPEN,RUNNING,FINISHED,CANCELLED`; `date_from` `nullable|date_format:Y-m-d`; `date_to` `nullable|date_format:Y-m-d|after_or_equal:date_from`; `sort_by` `nullable|in:<whitelist>`; `sort_dir` `nullable|in:asc,desc`; `per_page` `nullable|integer|min:1|max:100`; `page` `nullable|integer|min:1`.
3. File: `app/Http/Requests/StoreProductionResultRequest.php`
   - Rules: `wo_number` `required|string|exists:work_order,wo_number`; `actual_start` `required|date_format:Y-m-d H:i:s|before_or_equal:now`; `actual_finish` `required|date_format:Y-m-d H:i:s|after:actual_start|before_or_equal:now`; `good_qty` `required|integer|min:0`; `reject_qty` `required|integer|min:0`.
4. File: `app/Services/ProductionOrderService.php`
   - `public function paginate(array $filters): LengthAwarePaginator`
5. File: `app/Services/ProductionResultService.php`
   - `public function store(array $data): array` — transaksi + `lockForUpdate()`; lempar `ValidationException::withMessages(['wo_number' => ...])` bila status ≠ RUNNING atau WO sudah punya hasil; INSERT `production_result`; return baris yang dibuat.
   - Pesan: `Production order must be RUNNING (current status: {STATUS}).` dan `Production order already has a production result.`
6. File: `app/Http/Resources/ProductionOrderResource.php`
   - Item: `wo_number`, `product {code, name}`, `machine {code, name}`, `operator {employee_no, name}`, `shift`, `target_qty` (int), `plan_start`, `plan_finish`, `status`, `result` (`{actual_start, actual_finish, good_qty, reject_qty, achievement}` atau `null`).
7. File: `app/Http/Controllers/Api/ProductionOrderController.php`
   - `public function index(IndexProductionOrderRequest $request, ProductionOrderService $service): AnonymousResourceCollection`
8. File: `app/Http/Controllers/Api/ProductionResultController.php`
   - `public function store(StoreProductionResultRequest $request, ProductionResultService $service): JsonResponse` → HTTP 201, body `{"data": {id, wo_number, actual_start, actual_finish, runtime_minutes, good_qty, reject_qty, achievement}}`.

---

## 4. Scope of Changes

### A. Laravel

1. Dua FormRequest, dua service, satu resource, dua controller, dua route.

### B. Docs

1. `docs/features/api-production-orders.md` + registrasi di `docs/features/index.md` (termasuk contoh request/response tiap kasus penolakan POST, bahan README M6).
2. `docs/architecture.md`: lokasi kode Production Order API, hapus *(rencana)*.
3. Hapus item M4 dari `docs/backlog.md`.

---

## 5. Acceptance Test Matrix

Angka ekspektasi dihitung dari database saat planning.

| Case | Input | Expected Result | Status |
|---|---|---|---|
| Default list | `GET /api/production-orders` | 200; `meta.total` 1500, `meta.per_page` 15; item pertama `plan_start` 2026-12-31 23:00:00 (`WO2026001062`, sort sekunder `wo_number` desc) | `[x]` |
| Bentuk item | Item mana pun | Struktur nested `product`/`machine`/`operator`/`result`; `target_qty` & qty integer | `[x]` |
| `result` null | `?status=CANCELLED` | `meta.total` 26, semua `result` = `null` | `[x]` |
| Filter status | `?status=RUNNING` | `meta.total` 157 | `[x]` |
| Filter mesin + status | `?machine_code=MXA01&status=RUNNING` | `meta.total` 4 | `[x]` |
| Search | `?search=Mixing` | `meta.total` 119 | `[x]` |
| Filter tanggal | `?date_from=2026-01-01&date_to=2026-01-31` | `meta.total` 125 | `[x]` |
| Boundary — rentang satu hari | `?date_from=2026-12-31&date_to=2026-12-31` | Hanya WO dengan `DATE(plan_start)` = 2026-12-31, termasuk jam 23:00 | `[x]` |
| Sorting | `?sort_by=target_qty&sort_dir=asc` | `target_qty` item naik | `[x]` |
| Boundary — per_page | `?per_page=100` → 200; `?per_page=101` → 422 | | `[x]` |
| Failure — param tidak valid | `?status=FOO`, `?sort_by=password`, `?date_from=2026-02-01&date_to=2026-01-01`, `?date_from=31-01-2026` | 422 dengan `errors` pada field terkait | `[x]` |
| Link pagination | `?status=RUNNING&page=2` | `links.next` tetap membawa `status=RUNNING` | `[x]` |
| POST — WO tidak ada | `wo_number=WO9999` | 422 `errors.wo_number` | `[x]` |
| POST — qty negatif | `good_qty=-1`, `reject_qty=-1` | 422 `errors.good_qty`, `errors.reject_qty` | `[x]` |
| POST — tanggal masa depan | `actual_start` besok | 422 `errors.actual_start` | `[x]` |
| POST — finish sebelum start | `actual_finish < actual_start` | 422 `errors.actual_finish` | `[x]` |
| POST — format tanggal salah | `actual_start=2026-10-01` | 422 `errors.actual_start` | `[x]` |
| POST — status bukan RUNNING | WO `WO2026000005` (OPEN), `WO2026000001` (FINISHED), `WO2026000094` (CANCELLED) | 422 `errors.wo_number` "must be RUNNING (current status: …)" | `[x]` |
| POST — RUNNING sudah ada hasil | WO `WO2026000009` | 422 `errors.wo_number` "already has a production result" | `[x]` |
| POST — sukses (rollback) | Tinker: WO sementara RUNNING target 1000, start 08:00, finish 10:30, good 950, reject 10 | Baris dibuat: `runtime_minutes` 150, `achievement` 95.00; POST kedua ke WO yang sama ditolak; setelah rollback jumlah baris `production_result` tetap 1474 | `[x]` |
| Tidak ada perubahan data | Setelah semua uji | `work_order` 1500, `production_result` 1474, 8 tabel | `[x]` |

---

## 6. Verification Commands

1. `curl -s 'http://localhost/api/production-orders?<params>'`
2. `curl -s -X POST http://localhost/api/production-results -H 'Accept: application/json' -H 'Content-Type: application/json' -d '<json>'`
3. `docker compose exec app php artisan tinker` (skrip uji sukses dalam `DB::beginTransaction()` … `DB::rollBack()`)
4. `docker compose exec -T db mysql -uapp -psecret manufacturing_test -e 'SELECT COUNT(*) FROM work_order; SELECT COUNT(*) FROM production_result'`

Expected:

1. Semua case di Acceptance Test Matrix sesuai; jumlah baris data tidak berubah.

Hasil: semua sesuai. Uji sukses via tinker menghasilkan `runtime_minutes` 150, `achievement` 95, status WO tetap RUNNING, POST kedua ditolak; setelah rollback `work_order` 1500 dan `production_result` 1474. Efek samping: counter `AUTO_INCREMENT` `production_result` maju satu (id 1475 terpakai) karena rollback InnoDB tidak mengembalikan counter — tidak ada baris data yang berubah. Jalur 201 lewat HTTP tidak bisa diuji dengan data awal (D5).

---

## 7. Out of Scope

1. Endpoint create/update/delete Work Order dan perubahan status WO (R3).
2. Endpoint detail satu production order / list production result.
3. Autentikasi, rate limiting khusus.
4. Automated test (PRD §8).
5. Keputusan `APP_DEBUG` (backlog M6).

---

## 8. Completion Checklist

- [x] Section 1 fully filled and status set correctly.
- [x] All Non-Negotiable Technical Contract items implemented.
- [x] Acceptance Test Matrix completed.
- [x] Verification commands executed successfully.
- [x] No out-of-scope changes introduced.
