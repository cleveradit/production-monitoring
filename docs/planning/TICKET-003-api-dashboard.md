# Implementation Plan: TICKET-003 (API Dashboard)

**Ticket:** `TICKET-003`  
**Status:** `REVIEW`  
**Target Audience:** AI Developer Agents  
**Depends On:** `TICKET-001`

---

## 1. Business Decision Snapshot (Approved)

| Item | Approved Value |
|---|---|
| Milestone | M3 — PRD §9 |
| Endpoint | `GET /api/dashboard`, `GET /api/dashboard/machine/{id}` (PRD §7.5.1–7.5.2) |
| Tanggal acuan `D` | `DATE(MAX(production_result.actual_start))` (R6) — saat ini `2026-12-31` |
| `running_order`, `finished_order` | Seluruh data (K2) |
| `today_*` | Hasil dengan `DATE(actual_start) = D`, WO non-CANCELLED (B1, B4) |
| `top_machines` | Seluruh data, non-CANCELLED, `good_qty` DESC lalu `machine_code` ASC, maks 10 (K4) |
| `total_order` mesin | Semua status (K1) |
| `downtime_minutes` mesin | Semua WO mesin tersebut (WO CANCELLED tidak punya downtime di data — sudah dicek) |
| Achievement | `SUM(good) / SUM(target) × 100`, 2 desimal, number JSON; target 0 → `0` (B2, B3) |
| `{id}` | `machine_code`; tidak ditemukan → 404 JSON |
| Bentuk response | Objek JSON polos (tanpa wrapper `data`), sama dengan contoh soal + field tambahan `summary.data_date` dan `machine_code` |
| Routing API | Didaftarkan manual di `bootstrap/app.php`; **tidak** memakai `php artisan install:api` (memasang Sanctum + migration, melanggar R2) |
| Error JSON | Semua request `api/*` dirender sebagai JSON (`shouldRenderJsonWhen`) |
| Kasus `production_result` kosong | `data_date` = `null`, `today_*` = 0, `achievement` = 0, `trend_7_days` = `[]` |

---

## 2. Objective

Menyediakan dua endpoint read-only yang menjadi sumber data dashboard (M5) dan detail performa mesin. Angka dihitung relatif ke tanggal terbaru di data, bukan tanggal server, sehingga hasil stabil kapan pun dijalankan.

---

## 3. Non-Negotiable Technical Contract

1. File: `bootstrap/app.php`
   - `withRouting(... api: __DIR__.'/../routes/api.php' ...)`.
   - `withExceptions`: `$exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson())`.
2. File: `routes/api.php`
   - `Route::get('/dashboard', [DashboardController::class, 'index'])`
   - `Route::get('/dashboard/machine/{id}', [DashboardController::class, 'machine'])`
3. File: `app/Http/Controllers/Api/DashboardController.php`
   - `public function index(DashboardService $service): JsonResponse`
   - `public function machine(DashboardService $service, string $id): JsonResponse` — `abort(404, 'Machine not found.')` bila service mengembalikan `null`.
4. File: `app/Services/DashboardService.php`
   - `public function getDashboard(): array` → `['summary' => [...], 'trend_7_days' => [...], 'status_breakdown' => [...], 'top_machines' => [...]]`
   - `public function getMachinePerformance(string $machineCode): ?array`
   - Query memakai `DB::select()` dengan SQL + binding (CTE untuk agregasi per WO, B6); downtime diagregasi di CTE terpisah dari `production_result`.
   - Semua angka di-cast ke `int`/`float` di PHP (SUM MySQL mengembalikan DECIMAL → string di PDO).
   - Helper privat `achievement(int $good, int $target): float` → `$target > 0 ? round($good / $target * 100, 2) : 0`.
   - `trend_7_days`: tanggal `D-6` s.d. `D` dibentuk di PHP (`CarbonPeriod`), diisi dari hasil query, tanggal kosong → 0.
   - `status_breakdown`: urutan tetap `RUNNING, FINISHED, OPEN, CANCELLED`, status tanpa WO → 0.

Return shape `GET /api/dashboard`:

```json
{
  "summary": { "total_machine": 0, "running_order": 0, "finished_order": 0, "today_target": 0, "today_good": 0, "today_reject": 0, "achievement": 0.0, "data_date": "YYYY-MM-DD" },
  "trend_7_days": [ { "date": "YYYY-MM-DD", "good_qty": 0, "reject_qty": 0, "achievement": 0.0 } ],
  "status_breakdown": [ { "status": "RUNNING", "total": 0 } ],
  "top_machines": [ { "machine_code": "", "machine_name": "", "good_qty": 0, "achievement": 0.0 } ]
}
```

Return shape `GET /api/dashboard/machine/{id}`:

```json
{ "machine_code": "", "machine_name": "", "total_order": 0, "good_qty": 0, "reject_qty": 0, "downtime_minutes": 0, "achievement": 0.0 }
```

---

## 4. Scope of Changes

### A. Laravel

1. Registrasi routing API + render JSON untuk exception di `bootstrap/app.php`.
2. `routes/api.php`, `DashboardController`, `DashboardService`.

### B. Docs

1. `docs/features/api-dashboard.md` + registrasi di `docs/features/index.md`.
2. `docs/architecture.md`: isi lokasi kode Dashboard API, hapus *(rencana)*.
3. Hapus item M3 dari `docs/backlog.md`.

---

## 5. Acceptance Test Matrix

Angka ekspektasi dihitung langsung dari database saat planning.

| Case | Input | Expected Result | Status |
|---|---|---|---|
| Summary | `GET /api/dashboard` | `total_machine` 24, `running_order` 157, `finished_order` 1243, `today_target` 8572, `today_good` 7811, `today_reject` 26, `achievement` 91.12, `data_date` `2026-12-31` | `[ ]` |
| Boundary — trend dengan tanggal kosong | `trend_7_days` | Tepat 7 elemen 2026-12-25 … 2026-12-31; 2026-12-27 = `{good_qty: 0, reject_qty: 0, achievement: 0}`; 2026-12-25 = 8919 / 63 / 60.46 | `[ ]` |
| Status breakdown | `status_breakdown` | RUNNING 157, FINISHED 1243, OPEN 74, CANCELLED 26 (urutan tetap) | `[ ]` |
| Top machines | `top_machines` | 10 elemen; pertama `DSP02` High Speed Disperser 02 — 260210 / 88.11; terakhir `LBL02` — 176666 / 96.83 | `[ ]` |
| Detail mesin | `GET /api/dashboard/machine/MXA01` | `Mixing Tank 01`, `total_order` 59, `good_qty` 184727, `reject_qty` 1471, `downtime_minutes` 1527, `achievement` 94.56 | `[ ]` |
| Boundary — mesin dengan WO CANCELLED | `GET /api/dashboard/machine/WMX02` | `total_order` 57 (termasuk 4 CANCELLED), `good_qty` 132250, `reject_qty` 1109, `downtime_minutes` 1354, `achievement` 81.24 | `[ ]` |
| Failure — mesin tidak ada | `GET /api/dashboard/machine/XXX99` | HTTP 404, body JSON `{"message": "Machine not found."}` | `[ ]` |
| Failure — route API tidak ada | `GET /api/tidak-ada` | HTTP 404 JSON (bukan halaman HTML) | `[ ]` |
| Tipe data | Semua endpoint | Qty & total bertipe integer JSON, achievement number (bukan string) | `[ ]` |
| Tidak ada migrate | `SHOW TABLES` | Tetap 8 tabel dataset | `[ ]` |

---

## 6. Verification Commands

1. `curl -s http://localhost/api/dashboard | python3 -m json.tool`
2. `curl -s http://localhost/api/dashboard/machine/MXA01`
3. `curl -s -o /dev/null -w '%{http_code}' http://localhost/api/dashboard/machine/XXX99` → `404`
4. `docker compose exec app php artisan route:list --path=api`
5. `docker compose exec -T db mysql -uapp -psecret manufacturing_test -e 'SHOW TABLES'`

Expected:

1. Semua angka di Acceptance Test Matrix cocok.

---

## 7. Out of Scope

1. `GET /api/production-orders` dan `POST /api/production-results` (M4).
2. Halaman dashboard Vue (M5).
3. Autentikasi, Sanctum, rate limiting khusus, caching response.
4. Automated test (PRD §8).

---

## 8. Completion Checklist

- [x] Section 1 fully filled and status set correctly.
- [ ] All Non-Negotiable Technical Contract items implemented.
- [ ] Acceptance Test Matrix completed.
- [ ] Verification commands executed successfully.
- [ ] No out-of-scope changes introduced.
