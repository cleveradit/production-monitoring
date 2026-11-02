# API Production Orders

**Status:** Live

## Summary

`GET /api/production-orders` menampilkan daftar Work Order beserta produk, mesin, operator, dan hasil produksinya, dengan search, filter, sorting, dan pagination. Endpoint read-only. Input hasil produksi (`POST /api/production-results`) didokumentasikan di [API Production Results](api-production-results.md).

## Quick Reference

| Method | Path | Controller | Request | Service |
|---|---|---|---|---|
| GET | `/api/production-orders` | `Api\ProductionOrderController@index` | `IndexProductionOrderRequest` | `ProductionOrderService::paginate(array): LengthAwarePaginator` |

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

## Gotchas

- List mengambil hasil dengan `id` terbesar per WO (join ke subquery `MAX(id)`), sehingga WO tidak pernah muncul dua kali walau ada duplikat hasil.
- Sort selalu ditambah `wo_number` sebagai tie-breaker; tanpa itu, banyak WO dengan `plan_start` sama membuat isi halaman tidak stabil.
- Karakter `%` dan `_` pada `search` di-escape, sehingga dicari secara literal.

## Related

- [PRD §7.5.3](../initiate-file/prd.md#753-get-apiproduction-orders)
- [API Production Results](api-production-results.md)
- [API Dashboard](api-dashboard.md)
- [Data Model](../data-model.md)
