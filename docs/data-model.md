# Data Model Reference

Seluruh skema dan data berasal dari `database/dataset.sql` dan **tidak boleh diubah** (R1). Aplikasi tidak punya migration (R2); file ini adalah referensi skema satu-satunya.

## Data storage

| Storage | Isi | Keterangan |
|---|---|---|
| MySQL 8.4 — database `manufacturing_test` | 8 tabel dataset | Di-import otomatis dari `database/dataset.sql` saat volume DB kosong. Charset `utf8mb4`, collation `utf8mb4_unicode_ci`. |
| Filesystem `storage/` | Session, cache Laravel | Driver `file`, karena tabel bawaan Laravel tidak boleh dibuat. |

## Entities or tables

| Tabel | Purpose | Baris awal | Dipakai |
|---|---|---:|---|
| `work_order` | Perintah produksi (tabel pusat): produk, mesin, operator, shift, target, jadwal, status | 1.500 | Ya |
| `production_result` | Hasil aktual per WO | 1.474 | Ya (satu-satunya tabel yang di-INSERT aplikasi) |
| `product` | Master produk | 43 | Ya |
| `machine` | Master mesin | 24 | Ya |
| `employee` | Master operator | 60 | Ya |
| `downtime` | Waktu berhenti mesin per WO (bisa banyak baris per WO) | 1.258 | Ya |
| `maintenance` | Riwayat maintenance mesin | 316 | Tidak |
| `inventory_transaction` | Transaksi material | 4.223 | Tidak |

Definisi sumber: `database/dataset.sql`.

## Key fields

| Tabel | Field | Type | Rules or constraints |
|---|---|---|---|
| `employee` | `employee_no` | VARCHAR(10) | PK |
| `employee` | `full_name` | VARCHAR(100) | NOT NULL |
| `machine` | `machine_code` | VARCHAR(10) | PK (dipakai sebagai `{id}` di `/api/dashboard/machine/{id}`) |
| `machine` | `machine_name` | VARCHAR(100) | NOT NULL |
| `machine` | `production_line` | VARCHAR(50) | NOT NULL |
| `product` | `product_code` | VARCHAR(20) | PK |
| `product` | `product_name` | VARCHAR(150) | NOT NULL |
| `product` | `category` | VARCHAR(50) | NOT NULL |
| `product` | `target_min`, `target_max` | INT | NOT NULL |
| `work_order` | `wo_number` | VARCHAR(20) | PK |
| `work_order` | `product_code` | VARCHAR(20) | NOT NULL, FK → `product` |
| `work_order` | `machine_code` | VARCHAR(10) | NOT NULL, FK → `machine` |
| `work_order` | `employee_no` | VARCHAR(10) | NOT NULL, FK → `employee` |
| `work_order` | `shift` | VARCHAR(20) | NOT NULL |
| `work_order` | `target_qty` | INT | NOT NULL |
| `work_order` | `plan_start`, `plan_finish` | DATETIME | NOT NULL |
| `work_order` | `status` | VARCHAR(20) | NOT NULL; nilai lihat enum di bawah |
| `production_result` | `id` | INT | PK, AUTO_INCREMENT |
| `production_result` | `wo_number` | VARCHAR(20) | NOT NULL, FK → `work_order`; **tidak UNIQUE** |
| `production_result` | `actual_start`, `actual_finish` | DATETIME | NOT NULL; `DATE(actual_start)` = tanggal acuan produksi |
| `production_result` | `runtime_minutes` | INT | NOT NULL |
| `production_result` | `good_qty`, `reject_qty` | INT | NOT NULL |
| `production_result` | `achievement` | DECIMAL(8,2) | NOT NULL; per baris = `good_qty / target_qty × 100` |
| `downtime` | `id` | INT | PK, AUTO_INCREMENT |
| `downtime` | `wo_number` | VARCHAR(20) | NOT NULL, FK → `work_order` |
| `downtime` | `downtime_reason` | VARCHAR(100) | NOT NULL |
| `downtime` | `start_time`, `end_time` | DATETIME | NOT NULL |
| `downtime` | `duration_minutes` | INT | NOT NULL |

Index yang ada: `work_order(product_code)`, `(machine_code)`, `(employee_no)`, `(status)`, `(plan_start)`; `production_result(wo_number)`, `(actual_start)`; `downtime(wo_number)`, `(downtime_reason)`.

## Relations and enums

Relasi:

- `work_order` N—1 `product`, `machine`, `employee`.
- `production_result` N—1 `work_order` (secara data maksimal 1 per WO; dijaga aplikasi, bukan constraint).
- `downtime` N—1 `work_order`.
- Product ↔ Operator many-to-many **melalui `work_order`** (tidak ada pivot table).

Enum `work_order.status` (disimpan sebagai VARCHAR):

| Nilai | Jumlah awal | Catatan |
|---|---:|---|
| `FINISHED` | 1.243 | — |
| `RUNNING` | 157 | Semua sudah punya `production_result` |
| `OPEN` | 74 | Punya `production_result` dengan `good_qty` 0 |
| `CANCELLED` | 26 | Tidak punya `production_result`, tetapi tetap punya `target_qty` |

Shift: Shift 1 07:00–15:00, Shift 2 15:00–23:00, Shift 3 23:00–07:00 (melewati tengah malam).

Temuan data lain yang memengaruhi query: lihat [PRD §5.3](initiate-file/prd.md#53-temuan-data-penting).
