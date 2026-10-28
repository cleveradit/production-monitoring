# API Production Orders & Production Results

**Status:** Live

## Summary

`GET /api/production-orders` menampilkan daftar Work Order beserta produk, mesin, operator, dan hasil produksinya, dengan search, filter, sorting, dan pagination. `POST /api/production-results` adalah satu-satunya operasi tulis aplikasi: mencatat hasil produksi untuk WO berstatus RUNNING yang belum punya hasil. Dengan data awal semua WO RUNNING sudah punya hasil, sehingga endpoint POST selalu mengembalikan 422.

## Quick Reference

| Method | Path | Controller | Request | Service |
|---|---|---|---|---|
| GET | `/api/production-orders` | `Api\ProductionOrderController@index` | `IndexProductionOrderRequest` | `ProductionOrderService::paginate(array): LengthAwarePaginator` |
| POST | `/api/production-results` | `Api\ProductionResultController@store` | `StoreProductionResultRequest` | `ProductionResultService::store(array): array` |

### `GET /api/production-orders` — parameter

| Parameter | Aturan | Default |
|---|---|---|
| `search` | String ≤ 100; `LIKE` pada `wo_number`, `product_name`, `machine_name`, nama operator | — |
| `product_code` | Filter persis; kode tidak dikenal → list kosong | — |
| `machine_code` | Filter persis; kode tidak dikenal → list kosong | — |
| `status` | `OPEN`, `RUNNING`, `FINISHED`, `CANCELLED` | — |
| `date_from`, `date_to` | `Y-m-d`, filter tanggal `plan_start` (inklusif); `date_to ≥ date_from` | — |
| `sort_by` | `wo_number`, `plan_start`, `plan_finish`, `target_qty`, `status`, `product_name`, `machine_name` | `plan_start` |
| `sort_dir` | `asc`, `desc` | `desc` |
| `per_page` | Integer 1–100 | 15 |
| `page` | Integer ≥ 1 | 1 |

Parameter tidak valid → 422. Response: `data[]`, `links` (`first`, `last`, `prev`, `next` — filter ikut terbawa), `meta` (`current_page`, `per_page`, `total`, `last_page`, dst.).

Item `data[]`:

| Field | Isi |
|---|---|
| `wo_number`, `shift`, `status` | Dari `work_order` |
| `target_qty` | Integer |
| `plan_start`, `plan_finish` | `Y-m-d H:i:s` |
| `product` | `{code, name}` |
| `machine` | `{code, name}` |
| `operator` | `{employee_no, name}` |
| `result` | `{actual_start, actual_finish, good_qty, reject_qty, achievement}` atau `null` bila belum ada hasil |

### `POST /api/production-results` — body

| Field | Validasi |
|---|---|
| `wo_number` | required, ada di `work_order` |
| `actual_start` | required, `Y-m-d H:i:s`, ≤ waktu sekarang (`Asia/Jakarta`) |
| `actual_finish` | required, `Y-m-d H:i:s`, setelah `actual_start`, ≤ waktu sekarang |
| `good_qty` | required, integer ≥ 0 |
| `reject_qty` | required, integer ≥ 0 |

Aturan bisnis (dicek setelah format valid, di dalam transaksi): WO harus `RUNNING`, dan WO belum punya `production_result`. Nilai dihitung server: `runtime_minutes` (selisih menit) dan `achievement` (`good_qty / target_qty × 100`, 2 desimal). Status WO tidak diubah.

Sukses → HTTP 201:

```json
{ "data": { "id": 1476, "wo_number": "WO…", "actual_start": "2026-10-01 08:00:00", "actual_finish": "2026-10-01 10:30:00", "runtime_minutes": 150, "good_qty": 950, "reject_qty": 10, "achievement": 95 } }
```

### Contoh penolakan (semua HTTP 422)

| Kasus | Body (selain field valid) | `errors` |
|---|---|---|
| WO tidak ada | `"wo_number": "WO9999"` | `wo_number`: The selected wo number is invalid. |
| Qty negatif | `"good_qty": -1, "reject_qty": -1` | `good_qty`, `reject_qty`: must be at least 0. |
| Tanggal masa depan | `"actual_start"` > sekarang | `actual_start`: must be a date before or equal to now. |
| Finish sebelum start | `"actual_finish"` < `actual_start` | `actual_finish`: must be a date after actual start. |
| Format tanggal salah | `"actual_start": "2026-10-01"` | `actual_start`: must match the format Y-m-d H:i:s. |
| WO bukan RUNNING | `"wo_number": "WO2026000005"` (OPEN) | `wo_number`: Production order must be RUNNING (current status: OPEN). |
| WO RUNNING sudah punya hasil | `"wo_number": "WO2026000009"` | `wo_number`: Production order already has a production result. |

## Gotchas

- Dengan data awal, tidak ada WO yang bisa menerima POST: semua WO RUNNING sudah punya hasil (PRD D5). Ini perilaku yang disengaja.
- Tidak ada constraint UNIQUE pada `production_result.wo_number`. Batasan satu hasil per WO dijaga di `ProductionResultService` dengan `lockForUpdate()` pada baris `work_order` di dalam transaksi, sehingga dua request bersamaan tidak bisa sama-sama lolos.
- List mengambil hasil dengan `id` terbesar per WO (join ke subquery `MAX(id)`), sehingga WO tidak pernah muncul dua kali walau ada duplikat hasil.
- Sort selalu ditambah `wo_number` sebagai tie-breaker; tanpa itu, banyak WO dengan `plan_start` sama membuat isi halaman tidak stabil.
- Karakter `%` dan `_` pada `search` di-escape, sehingga dicari secara literal.
- Jalur sukses POST hanya bisa diuji tanpa mengubah data permanen lewat transaksi yang di-rollback (`tinker`). Rollback InnoDB tidak mengembalikan counter `AUTO_INCREMENT`, jadi id berikutnya bisa melompat; reset dengan `docker compose down -v`.

## Related

- [PRD §7.5.3–7.5.4](../initiate-file/prd.md#753-get-apiproduction-orders)
- [API Dashboard](api-dashboard.md)
- [Data Model](../data-model.md)
