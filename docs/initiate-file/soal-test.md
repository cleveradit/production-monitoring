# Soal Technical Test — Software Engineer Staff

Transkripsi soal asli (sumber: dokumen technical test Avian Brands). File ini adalah **sumber kebenaran** untuk isi soal. Tafsiran dan keputusan ada di [prd.md](prd.md).

Contoh tabel/response di bawah disalin apa adanya. Beberapa angka contoh hanya ilustrasi dan tidak semuanya cocok dengan dataset (lihat [prd.md §5.3](prd.md#53-temuan-data-penting)).

---

## Pengantar

PT XYZ Manufacturing ingin membangun **Production Monitoring System** untuk memonitor aktivitas produksi seluruh pabrik.

Database **sudah disediakan** dan dapat langsung digunakan. Kandidat **tidak diperbolehkan mengubah struktur tabel maupun isi data**.

Seluruh pengerjaan menggunakan:

- Laravel
- MySQL/MariaDB
- Docker

---

## Bagian A — Database Operation (50%)

### Soal 1 — Many-to-Many Query (5%)

Dalam sistem terdapat hubungan many-to-many antara Product dan Operator (satu produk dikerjakan pada banyak Work Order oleh operator berbeda-beda, dan satu operator dapat mengerjakan lebih dari satu produk).

Buat query SQL untuk menampilkan informasi berikut:

| Product | Total WO | Total Operator | Nama Operator |
|---|---:|---:|---|
| Avitex Alkali Resisting Primer 20 Kg | 55 | 34 | Adi Putra, Adi Utama, Agus Putra, Andi Wibowo, … |
| Avian Floor Coat Green 5 Kg | 46 | 27 | Adi Putra, Adi Utama, Andi Wibowo, Bayu Wibowo, Budi Ramadhan, … |
| Avitex Wall Sealer 20 Kg | 74 | 26 | Adi Putra, Adi Utama, Asep Maulana, Asep Wijaya, Bayu Wibowo, … |
| dst… | dst… | dst… | dst… |

Ketentuan:

- Satu operator hanya dihitung satu kali per produk, walaupun mengerjakan WO dari produk tersebut lebih dari satu kali.
- Nama operator ditampilkan dalam satu kolom, dipisahkan tanda koma.
- Urutkan berdasarkan Total Operator terbanyak.

### Soal 2 — CTE Dasar (10%)

Supervisor ingin mengetahui pencapaian produksi tiap Product (akumulasi dari seluruh Work Order untuk produk tersebut).

Buat query menggunakan Common Table Expression (WITH) untuk menghasilkan:

| Product | Total WO | Target | Good Qty | Reject Qty | Achievement (%) |
|---|---:|---:|---:|---:|---:|
| Avian Synthetic Enamel Black 5 Kg | 38 | 87009 | 84416 | 679 | 97 |
| No Drop Putih 20 Kg | 34 | 47796 | 46249 | 408 | 96 |
| Giant Mortar Plaster 40 Kg | 30 | 27870 | 26725 | 209 | 95 |
| dst… | dst… | dst… | dst… | dst… | dst… |

Keterangan:

```
Achievement = Good Qty / Target × 100
```

Urutkan berdasarkan achievement terbesar.

Ketentuan:

- Wajib menggunakan **CTE (WITH)**.
- Tidak diperbolehkan menggunakan subquery sebagai pengganti CTE.

### Soal 3 — CTE + Window Function (15%)

Management ingin mengetahui performa mesin.

Buat query yang menghasilkan informasi berikut:

| Machine | Total Good | Total Reject | Achievement | Ranking |
|---|---:|---:|---:|---:|
| Labelling Machine 02 | 176666 | 1572 | 97 | 1 |
| Mixing Tank 01 | 184727 | 1471 | 95 | 2 |
| Paint Disperser 02 | 164486 | 1402 | 94 | 3 |
| dst… | dst… | dst… | dst… | dst… |

Ketentuan:

- Achievement dihitung berdasarkan total good dibanding target produksi.
- Apabila terdapat nilai yang sama maka ranking harus sama.
- Gunakan CTE sebagai tahap agregasi sebelum Window Function.

### Soal 4 — Recursive CTE (20%)

Buat query trend performa produksi dengan drill-down tiga level: **Tahun → Bulan → Tanggal**, termasuk tanggal yang tidak memiliki data produksi (tetap tampil dengan nilai 0).

Output:

| Date | Total Good | Total Reject | Achievement |
|---|---:|---:|---:|
| 2026-01-02 | 5223 | 17 | 97 |
| 2026-01-03 | 14866 | 111 | 100 |
| 2026-01-04 | 6363 | 52 | 86 |
| dst… | dst… | dst… | dst… |

Ketentuan:

- Deret tanggal harian wajib digenerate dengan **Recursive CTE** (bukan hardcode, bukan tabel kalender terpisah) sebagai basis sebelum di-rollup.

---

## Bagian B — Laravel Development (40%)

Gunakan database yang telah disediakan. Tidak diperbolehkan mengubah struktur database.

### Soal 5 — REST API (20%)

Buat API berikut.

#### 1. `GET /api/dashboard`

Endpoint untuk menampilkan performa secara global, termasuk data pendukung dashboard (trend, breakdown status, top machine). Response minimal:

```json
{
  "summary": {
    "total_machine": 20,
    "running_order": 45,
    "finished_order": 200,
    "today_target": 125000,
    "today_good": 118300,
    "today_reject": 850,
    "achievement": 94.64
  },
  "trend_7_days": [
    { "date": "2026-07-16", "good_qty": 11800, "reject_qty": 90, "achievement": 96.1 },
    { "date": "2026-07-17", "good_qty": 0, "reject_qty": 0, "achievement": 0 }
  ],
  "status_breakdown": [
    { "status": "RUNNING", "total": 45 },
    { "status": "FINISHED", "total": 200 },
    { "status": "OPEN", "total": 12 },
    { "status": "CANCELLED", "total": 3 }
  ],
  "top_machines": [
    { "machine_code": "MXA01", "machine_name": "Mixer A01", "good_qty": 10000, "achievement": 97.3 }
  ]
}
```

Ketentuan:

- a. `today_*` dan `trend_7_days` dihitung relatif terhadap tanggal terbaru yang ada di data (`MAX(actual_start)` pada `production_result`), **bukan** `CURDATE()` server — supaya hasil tidak bergantung kapan test dijalankan.
- b. `trend_7_days` wajib mencakup semua 7 tanggal berurutan meski tidak ada data produksi pada tanggal tersebut (tampil dengan nilai 0, bukan hilang dari array).
- c. `top_machines` diurutkan berdasarkan `good_qty` terbesar, maksimal 10 item.

#### 2. `GET /api/dashboard/machine/{id}`

Endpoint untuk menampilkan performa sebuah mesin. Response minimal:

```json
{
  "machine_name": "Mixer A01",
  "total_order": 25,
  "good_qty": 10000,
  "reject_qty": 125,
  "downtime_minutes": 210,
  "achievement": 97.3
}
```

#### 3. `GET /api/production-orders`

Endpoint untuk menampilkan list production order, harus mendukung: search, product, machine, status, date, pagination, sorting.

#### 4. `POST /api/production-results`

Endpoint untuk mengirim hasil produksi dengan menerapkan validasi:

- qty_good ≥ 0
- qty_reject ≥ 0
- production_date tidak boleh melebihi hari ini
- Production Order harus berstatus RUNNING

### Soal 6 — Dashboard Web (20%)

Buat halaman dashboard menggunakan Laravel Blade atau frontend framework pilihan (React, Vue, jQuery, atau Vanilla JavaScript).

Gunakan **Tailwind CSS** sebagai framework utama.

Dashboard minimal menampilkan:

**KPI**

- Total Machine
- Running Work Order
- Finished Work Order
- Achievement
- Good Qty
- Reject Qty

**Tabel**

- Top 10 Machine Performance

**Grafik** — minimal terdiri dari:

- Trend produksi 7 hari terakhir (Line Chart)
- Status Work Order (Pie/Donut Chart)
- Top 10 Mesin berdasarkan Good Qty (Bar Chart)

Seluruh data di atas diambil dari satu response `GET /api/dashboard` pada Soal 5 (field `summary`, `trend_7_days`, `status_breakdown`, `top_machines`).

Yang dinilai:

- Tampilan modern dan responsif
- Penggunaan Tailwind CSS
- Interaktivitas (AJAX/Fetch API/React/Vue)
- Integrasi dengan REST API
- Struktur kode frontend

---

## Bagian C — Deployment (10%)

### Soal 7 — Docker Containerization

Aplikasi harus dapat dijalankan menggunakan Docker.

Minimal terdiri dari:

- PHP
- Nginx
- MariaDB

Gunakan Docker Compose.

Aplikasi harus dapat dijalankan menggunakan:

```bash
docker compose up -d
```

Buat file `README.md` yang menjelaskan:

1. Cara menjalankan aplikasi menggunakan Docker.
2. Konfigurasi environment (`.env`).
3. Langkah import database yang disediakan.
4. URL aplikasi dan endpoint API.
