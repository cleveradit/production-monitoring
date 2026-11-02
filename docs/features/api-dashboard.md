# API Dashboard

**Status:** Live

## Summary

Dua endpoint read-only yang menyediakan data dashboard global dan performa per mesin. Semua angka "hari ini" dan trend dihitung relatif ke tanggal produksi terbaru di data (`DATE(MAX(production_result.actual_start))`), bukan tanggal server. Logika query ada di `DashboardService`; controller hanya meneruskan hasilnya sebagai JSON.

## Quick Reference

| Method | Path | Controller | Response |
|---|---|---|---|
| GET | `/api/dashboard` | `Api\DashboardController@index` | `summary`, `trend_7_days`, `status_breakdown`, `top_machines` |
| GET | `/api/dashboard/machine/{id}` | `Api\DashboardController@machine` | Performa satu mesin; `{id}` = `machine_code`; 404 bila tidak ada |

### `GET /api/dashboard`

| Field | Definisi |
|---|---|
| `summary.total_machine` | Jumlah baris `machine` |
| `summary.running_order` / `finished_order` | Jumlah WO berstatus RUNNING / FINISHED di seluruh data |
| `summary.today_target` / `today_good` / `today_reject` | SUM untuk hasil dengan `DATE(actual_start) = data_date`, WO non-CANCELLED |
| `summary.achievement` | `today_good / today_target × 100`, 2 desimal |
| `summary.data_date` | Tanggal acuan (`YYYY-MM-DD`); `null` bila `production_result` kosong |
| `trend_7_days[]` | Tepat 7 elemen `data_date - 6` … `data_date`: `date`, `good_qty`, `reject_qty`, `achievement`; tanggal tanpa data bernilai 0 |
| `status_breakdown[]` | Selalu 4 elemen urut RUNNING, FINISHED, OPEN, CANCELLED: `status`, `total` |
| `top_machines[]` | Maks 10 mesin, seluruh data non-CANCELLED, urut `good_qty` DESC lalu `machine_code` ASC: `machine_code`, `machine_name`, `good_qty`, `achievement` |

### `GET /api/dashboard/machine/{id}`

| Field | Definisi |
|---|---|
| `machine_code`, `machine_name` | Dari tabel `machine` |
| `total_order` | Jumlah WO mesin, semua status |
| `good_qty`, `reject_qty` | SUM hasil WO non-CANCELLED |
| `downtime_minutes` | SUM `downtime.duration_minutes` untuk semua WO mesin |
| `achievement` | `good_qty / SUM(target_qty non-CANCELLED) × 100`, 2 desimal |

### Kode

| File | Isi |
|---|---|
| `routes/api.php` | Definisi route |
| `app/Http/Controllers/Api/DashboardController.php` | `index()`, `machine()` |
| `app/Services/DashboardService.php` | `getDashboard(): array`, `getMachinePerformance(string): ?array` |
| `bootstrap/app.php` | Registrasi `routes/api.php` dan render JSON untuk exception `api/*` |

## Gotchas

- Routing API didaftarkan manual di `bootstrap/app.php`. Jangan jalankan `php artisan install:api`: perintah itu memasang Sanctum beserta migration tabel token (melanggar R2).
- SUM MySQL dikembalikan PDO sebagai string (DECIMAL); semua angka di-cast ke `int`/`float` di service agar JSON bertipe number.
- `machine_code` dicocokkan case-insensitive (collation `utf8mb4_unicode_ci`): `/machine/mxa01` mengembalikan `MXA01`.
- Hasil produksi dan downtime diagregasi per WO di CTE terpisah sebelum di-join; join langsung keduanya akan menggandakan good/reject untuk WO dengan banyak baris downtime.
- Filter tanggal memakai rentang `actual_start >= D AND < D + 1 hari` (setara `DATE(actual_start) = D`) agar index `actual_start` terpakai.
- `.env.example` memakai `APP_DEBUG=false`, sehingga body error JSON hanya `{"message": "..."}` (422 tetap berisi `errors`). Dengan `APP_DEBUG=true`, body ikut berisi `exception`, `file`, dan `trace`. `.env` yang sudah ada tidak ikut berubah saat `.env.example` diubah.

## Related

- [PRD §7.5.1–7.5.2](../initiate-file/prd.md#751-get-apidashboard)
- [Data Model](../data-model.md)
- [SQL Bagian A](sql-bagian-a.md)
