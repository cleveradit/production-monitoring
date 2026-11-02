# Production Monitoring System

Technical test Software Engineer Staff — studi kasus PT XYZ Manufacturing:

- **Bagian A** — query SQL analitik (many-to-many, CTE, window function, Recursive CTE + ROLLUP) di folder `sql/`.
- **Bagian B** — REST API Laravel 12 dan dashboard Vue 3 + Tailwind CSS + Chart.js.
- **Bagian C** — seluruh aplikasi berjalan dengan satu perintah `docker compose up -d`.

| Komponen | Versi |
|---|---|
| PHP (FPM) | 8.3 |
| Laravel | 12 |
| Database | MySQL 8.4 LTS (lihat [Deviasi MySQL vs MariaDB](#deviasi-mysql-vs-mariadb)) |
| Web server | Nginx 1.27 (alpine) |
| Frontend | Vue 3, Tailwind CSS v4, Chart.js — di-build dengan Vite |
| Node (build aset) | 22 (alpine) |

Struktur tabel dan data awal tidak diubah sama sekali: tidak ada migration, dan satu-satunya operasi tulis aplikasi adalah `POST /api/production-results`.

---

## Daftar Isi

1. [Menjalankan dengan Docker](#menjalankan-dengan-docker)
2. [Konfigurasi `.env`](#konfigurasi-env)
3. [Import database](#import-database)
4. [URL aplikasi dan endpoint API](#url-aplikasi-dan-endpoint-api)
5. [Perilaku `POST /api/production-results`](#perilaku-post-apiproduction-results)
6. [Query SQL Bagian A](#query-sql-bagian-a)
7. [Deviasi MySQL vs MariaDB](#deviasi-mysql-vs-mariadb)
8. [Asumsi bisnis](#asumsi-bisnis)
9. [Struktur project](#struktur-project)
10. [Troubleshooting](#troubleshooting)
11. [Di luar lingkup](#di-luar-lingkup)

---

## Menjalankan dengan Docker

**Prasyarat:** Docker dan Docker Compose v2. PHP, Composer, dan Node di host **tidak** diperlukan. Port host `80` dan `3307` harus bebas.

```bash
git clone <url-repo> production-monitoring
cd production-monitoring
docker compose up -d
```

Buka **http://localhost**.

Tidak ada langkah manual lain. Saat pertama kali dijalankan, Docker Compose otomatis:

| Urutan | Service | Yang terjadi |
|---|---|---|
| 1 | `db` (MySQL 8.4) | Import `database/dataset.sql` ke database `manufacturing_test` (hanya saat volume masih kosong). Dianggap siap setelah import selesai (healthcheck). |
| 2 | `node` (sekali jalan) | `npm ci && npm run build` → aset Vite di `public/build/`. |
| 3 | `app` (PHP-FPM) | Menunggu `db` siap dan `node` selesai, lalu menyalin `.env.example` → `.env` bila belum ada, `composer install` bila `vendor/` belum ada, `php artisan key:generate` bila `APP_KEY` kosong, dan mengatur permission `storage/` + `bootstrap/cache/`. |
| 4 | `nginx` | Melayani `http://localhost` dan meneruskan PHP ke `app:9000`. |

Start pertama butuh beberapa menit (build image PHP, import dataset, `composer install`, `npm ci`). Start berikutnya hanya beberapa detik.

| Tugas | Perintah |
|---|---|
| Start | `docker compose up -d` |
| Status service | `docker compose ps` |
| Log aplikasi | `docker compose logs -f app` |
| Stop | `docker compose down` |
| Stop + hapus database (import ulang saat start berikutnya) | `docker compose down -v` |
| Rebuild image PHP | `docker compose up -d --build` |
| Build ulang aset frontend | `docker compose run --rm node` |
| Perintah Artisan | `docker compose exec app php artisan <perintah>` |
| Bersihkan cache Laravel | `docker compose exec app php artisan optimize:clear` |

> Jangan menjalankan `php artisan migrate` dalam bentuk apa pun. Skema database berasal dari dataset dan tidak boleh diubah.

---

## Konfigurasi `.env`

`.env` dibuat otomatis dari `.env.example` saat container `app` pertama kali start, dan `APP_KEY` di-generate otomatis. `.env.example` sudah berisi konfigurasi untuk Docker, sehingga tidak ada yang wajib diubah.

| Variabel | Nilai | Keterangan |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Error API hanya berisi `{"message": "..."}`. Ubah ke `true` saat development untuk melihat stack trace. |
| `APP_KEY` | *(kosong)* | Diisi otomatis oleh entrypoint. |
| `APP_URL` | `http://localhost` | Sesuaikan bila port Nginx diubah. |
| `APP_TIMEZONE` | `Asia/Jakarta` | Dipakai validasi "tidak boleh melebihi waktu sekarang" pada POST. |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `db` | Nama service Docker, bukan `localhost`. |
| `DB_PORT` | `3306` | Port di dalam jaringan Docker. |
| `DB_DATABASE` | `manufacturing_test` | Dibuat oleh `dataset.sql`. |
| `DB_USERNAME` / `DB_PASSWORD` | `app` / `secret` | Dibuat container MySQL dari `docker-compose.yml`. |
| `SESSION_DRIVER` | `file` | Driver default Laravel (`database`) butuh tabel tambahan, padahal skema tidak boleh diubah. |
| `CACHE_STORE` | `file` | Alasan sama. |
| `QUEUE_CONNECTION` | `sync` | Alasan sama. |

Bila `.env` diubah setelah container berjalan, jalankan `docker compose exec app php artisan optimize:clear`. Mengedit `.env.example` tidak berpengaruh bila `.env` sudah ada.

---

## Import database

**Otomatis.** `database/dataset.sql` (salinan asli, tidak diedit) di-mount ke `/docker-entrypoint-initdb.d/` di container MySQL. Import berjalan sekali, saat volume `db-data` masih kosong, yaitu pada `docker compose up -d` pertama.

**Import ulang** dari awal (menghapus semua data, termasuk hasil `POST`):

```bash
docker compose down -v
docker compose up -d
```

**Import manual** ke database yang sedang berjalan (opsional; `dataset.sql` menjalankan `DROP DATABASE` + `CREATE DATABASE`):

```bash
docker compose exec -T db mysql -uroot -proot < database/dataset.sql
```

**Cek hasil import:**

```bash
docker compose exec db mysql -uapp -psecret manufacturing_test -e "SELECT COUNT(*) FROM work_order;"
# 1500
```

**Koneksi dari host** (DBeaver, TablePlus, dll.): host `127.0.0.1`, port `3307`, database `manufacturing_test`, user `app` / `secret` (atau `root` / `root`).

---

## URL aplikasi dan endpoint API

| Method | URL | Keterangan |
|---|---|---|
| GET | http://localhost/ | Dashboard (KPI, tabel Top 10 mesin, 3 grafik) |
| GET | http://localhost/api/dashboard | Seluruh data dashboard dalam satu response |
| GET | http://localhost/api/dashboard/machine/{id} | Performa satu mesin; `{id}` = `machine_code`, mis. `MXA01` |
| GET | http://localhost/api/production-orders | Daftar Work Order dengan search, filter, sort, pagination |
| POST | http://localhost/api/production-results | Input hasil produksi |

Semua endpoint mengembalikan JSON. Error validasi → HTTP 422 (format standar Laravel: `message` + `errors`). Resource atau route tidak ditemukan → HTTP 404 JSON.

### `GET /api/dashboard`

"Hari ini" adalah tanggal produksi terakhir di data, `DATE(MAX(production_result.actual_start))` = **2026-12-31**, bukan tanggal server (data berisi tanggal di masa depan).

```bash
curl http://localhost/api/dashboard
```

```jsonc
{
  "summary": {
    "total_machine": 24, "running_order": 157, "finished_order": 1243,
    "today_target": 8572, "today_good": 7811, "today_reject": 26,
    "achievement": 91.12, "data_date": "2026-12-31"
  },
  "trend_7_days": [
    { "date": "2026-12-25", "good_qty": 8919, "reject_qty": 63, "achievement": 60.46 },
    // … 2026-12-26
    { "date": "2026-12-27", "good_qty": 0, "reject_qty": 0, "achievement": 0 }
    // … s.d. 2026-12-31 (tepat 7 elemen; tanggal tanpa produksi bernilai 0)
  ],
  "status_breakdown": [
    { "status": "RUNNING", "total": 157 }, { "status": "FINISHED", "total": 1243 },
    { "status": "OPEN", "total": 74 }, { "status": "CANCELLED", "total": 26 }
  ],
  "top_machines": [
    { "machine_code": "DSP02", "machine_name": "High Speed Disperser 02", "good_qty": 260210, "achievement": 88.11 }
    // … maks. 10 mesin, urut good_qty DESC
  ]
}
```

| Field | Definisi |
|---|---|
| `summary.total_machine` | Jumlah mesin |
| `summary.running_order`, `finished_order` | Jumlah WO RUNNING / FINISHED di seluruh data |
| `summary.today_target`, `today_good`, `today_reject`, `achievement` | Hasil produksi dengan `DATE(actual_start) = data_date`, tanpa WO CANCELLED |
| `trend_7_days` | Tepat 7 hari sampai `data_date` |
| `status_breakdown` | Jumlah WO per status (keempat status) |
| `top_machines` | 10 mesin dengan good qty terbesar di seluruh data (tanpa WO CANCELLED) |

### `GET /api/dashboard/machine/{id}`

```bash
curl http://localhost/api/dashboard/machine/MXA01
```

```json
{ "machine_code": "MXA01", "machine_name": "Mixing Tank 01", "total_order": 59, "good_qty": 184727, "reject_qty": 1471, "downtime_minutes": 1527, "achievement": 94.56 }
```

`total_order` dan `downtime_minutes` menghitung semua WO mesin; `good_qty`, `reject_qty`, dan `achievement` tanpa WO CANCELLED. Mesin tidak ada → `404 {"message": "Machine not found."}`. Kode mesin tidak case-sensitive.

### `GET /api/production-orders`

```bash
curl "http://localhost/api/production-orders?search=enamel&status=RUNNING&sort_by=plan_start&sort_dir=asc&per_page=10"
```

| Parameter | Aturan | Default |
|---|---|---|
| `search` | Teks ≤ 100 karakter; dicari di nomor WO, nama produk, nama mesin, nama operator | — |
| `product_code` | Kode produk persis | — |
| `machine_code` | Kode mesin persis | — |
| `status` | `OPEN`, `RUNNING`, `FINISHED`, `CANCELLED` | — |
| `date_from`, `date_to` | `YYYY-MM-DD`, filter tanggal `plan_start` (inklusif) | — |
| `sort_by` | `wo_number`, `plan_start`, `plan_finish`, `target_qty`, `status`, `product_name`, `machine_name` | `plan_start` |
| `sort_dir` | `asc`, `desc` | `desc` |
| `per_page` | 1–100 | 15 |
| `page` | ≥ 1 | 1 |

Response mengikuti paginator Laravel (`data`, `links`, `meta`). Contoh satu item `data[]`:

```json
{
  "wo_number": "WO2026000962",
  "product": { "code": "NDP0003", "name": "No Drop Putih 20 Kg" },
  "machine": { "code": "MXA01", "name": "Mixing Tank 01" },
  "operator": { "employee_no": "EMP0045", "name": "Rangga Hakim" },
  "shift": "Shift 1", "target_qty": 1439,
  "plan_start": "2026-09-27 07:00:00", "plan_finish": "2026-09-27 15:00:00",
  "status": "RUNNING",
  "result": { "actual_start": "2026-09-27 07:12:00", "actual_finish": "2026-09-27 15:20:00", "good_qty": 475, "reject_qty": 4, "achievement": 33.01 }
}
```

`result` bernilai `null` bila WO belum punya hasil produksi. Parameter tidak valid (mis. `status=XX`) → 422.

---

## Perilaku `POST /api/production-results`

Mencatat hasil produksi untuk satu Work Order. Ini satu-satunya operasi tulis aplikasi (INSERT ke `production_result`); status WO **tidak** diubah.

**Body (JSON):**

| Field | Validasi |
|---|---|
| `wo_number` | Wajib, harus ada di `work_order` |
| `actual_start` | Wajib, format `YYYY-MM-DD HH:MM:SS`, tidak boleh melebihi waktu sekarang (Asia/Jakarta) |
| `actual_finish` | Wajib, format sama, setelah `actual_start`, tidak boleh melebihi waktu sekarang |
| `good_qty` | Wajib, integer ≥ 0 |
| `reject_qty` | Wajib, integer ≥ 0 |

Setelah format valid, dicek aturan bisnis di dalam transaksi: (1) status WO harus `RUNNING`, (2) WO belum punya hasil produksi (satu WO maksimal satu hasil). Server menghitung `runtime_minutes` (selisih menit) dan `achievement` (`good_qty / target_qty × 100`, 2 desimal). Sukses → HTTP 201 dengan baris yang dibuat.

> **Penting — dengan data awal, endpoint ini selalu mengembalikan 422.** Semua WO berstatus `RUNNING` di dataset sudah punya hasil produksi, sedangkan WO berstatus lain ditolak. Ini perilaku yang disengaja: aplikasi tidak boleh mengubah data awal (termasuk status WO), jadi tidak ada WO yang memenuhi kedua aturan. Response 201 hanya bisa terjadi bila ada WO `RUNNING` tanpa hasil.

Contoh setiap kasus penolakan (response nyata dari aplikasi):

**1. WO RUNNING yang sudah punya hasil**

```bash
curl -X POST http://localhost/api/production-results \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"wo_number":"WO2026000009","actual_start":"2026-10-01 08:00:00","actual_finish":"2026-10-01 10:30:00","good_qty":950,"reject_qty":10}'
```

```json
HTTP 422
{"message":"Production order already has a production result.","errors":{"wo_number":["Production order already has a production result."]}}
```

**2. WO yang tidak berstatus RUNNING** (`WO2026000001` = FINISHED)

```json
HTTP 422
{"message":"Production order must be RUNNING (current status: FINISHED).","errors":{"wo_number":["Production order must be RUNNING (current status: FINISHED)."]}}
```

**3. Nomor WO tidak ada**

```json
HTTP 422
{"message":"The selected wo number is invalid.","errors":{"wo_number":["The selected wo number is invalid."]}}
```

**4. Format/nilai tidak valid** (`actual_start` di masa depan, `actual_finish` sebelum `actual_start`, qty negatif/bukan angka)

```bash
curl -X POST http://localhost/api/production-results \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"wo_number":"WO2026000009","actual_start":"2099-01-01 08:00:00","actual_finish":"2026-10-01 07:00:00","good_qty":-1,"reject_qty":"abc"}'
```

```json
HTTP 422
{
  "message": "The actual start field must be a date before or equal to now. (and 3 more errors)",
  "errors": {
    "actual_start": ["The actual start field must be a date before or equal to now."],
    "actual_finish": ["The actual finish field must be a date after actual start."],
    "good_qty": ["The good qty field must be at least 0."],
    "reject_qty": ["The reject qty field must be an integer."]
  }
}
```

**Contoh response sukses** (bila ada WO RUNNING tanpa hasil):

```json
HTTP 201
{ "data": { "id": 1476, "wo_number": "WO…", "actual_start": "2026-10-01 08:00:00", "actual_finish": "2026-10-01 10:30:00", "runtime_minutes": 150, "good_qty": 950, "reject_qty": 10, "achievement": 95 } }
```

Nama field qty memakai nama kolom database: `good_qty` / `reject_qty` (soal menulis `qty_good` / `qty_reject`), dan "production_date" di soal dipetakan ke `actual_start` (lihat B7–B8).

---

## Query SQL Bagian A

| Soal | File | Teknik |
|---|---|---|
| 1 — Many-to-Many | `sql/soal-1-many-to-many.sql` | `COUNT(DISTINCT)` + `GROUP_CONCAT(DISTINCT … ORDER BY …)` |
| 2 — CTE Dasar | `sql/soal-2-cte-product-achievement.sql` | CTE bernama (tanpa subquery pengganti) |
| 3 — CTE + Window Function | `sql/soal-3-machine-ranking.sql` | Agregasi di CTE lalu `RANK()` |
| 4 — Recursive CTE + Rollup | `sql/soal-4-recursive-trend.sql` | `WITH RECURSIVE` deret tanggal dari MIN/MAX data + `WITH ROLLUP` + `GROUPING()` |

Setiap file berisi komentar asumsi di bagian atas. Menjalankan query terhadap database di container:

```bash
docker compose exec -T db mysql -uapp -psecret manufacturing_test --table < sql/soal-2-cte-product-achievement.sql
```

Angka acuan untuk verifikasi:

| Soal | Hasil yang diharapkan |
|---|---|
| 1 | Avitex Alkali Resisting Primer 20 Kg → 55 WO, 34 operator |
| 2 | Baris teratas: Avian Synthetic Enamel Black 5 Kg → 38 / 87009 / 84416 / 679 / 97 |
| 3 | Ranking 1: Labelling Machine 02 → 176666 / 1572 / 97 |
| 4 | 2026-01-02 → 5223 / 17 / 97; 2026-12-27 muncul dengan 0 / 0 / 0; baris terakhir `GRAND TOTAL` |

---

## Deviasi MySQL vs MariaDB

Soal 7 menyebut MariaDB sebagai container minimal. Project ini memakai **MySQL 8.4 LTS**. Alasannya:

1. **Soal 4 membutuhkan `GROUPING()`** untuk membedakan baris DATE / MONTH / YEAR / GRAND TOTAL hasil `WITH ROLLUP`. MariaDB tidak mendukung fungsi ini. Sudah diuji di MariaDB 11.4.13: `ERROR 1305 (42000): FUNCTION GROUPING does not exist`. Tanpa `GROUPING()`, baris subtotal hanya bisa dikenali dari nilai `NULL`, yang rawan ambigu.
2. Bagian A dan B pada soal membolehkan **MySQL/MariaDB**, dan dataset (`utf8mb4_unicode_ci`) berjalan tanpa perubahan di MySQL 8.4.
3. Fitur lain yang dipakai (Recursive CTE, window function `RANK()`, `ROLLUP`) tersedia di keduanya, jadi selain Soal 4 tidak ada ketergantungan khusus MySQL.

Untuk beralih ke MariaDB cukup mengganti image service `db`, tetapi query Soal 4 harus ditulis ulang tanpa `GROUPING()`.

---

## Asumsi bisnis

Hasil analisis dataset menentukan beberapa keputusan yang tidak tertulis eksplisit di soal:

| # | Topik | Keputusan | Dasar |
|---|---|---|---|
| B1 | WO CANCELLED | Target, good, reject, achievement, dan "Total WO" laporan produksi **tidak** menghitung WO CANCELLED. `status_breakdown` tetap menampilkan keempat status. | WO CANCELLED tidak punya hasil produksi tetapi punya target, sehingga akan menurunkan achievement. Contoh Soal 2 (Enamel Black: 38 WO dari 40, 2 CANCELLED) dan Soal 3 hanya cocok bila CANCELLED dikecualikan. |
| B2 | Rumus achievement agregat | `SUM(good_qty) / SUM(target_qty) × 100`; target 0 → 0 | Bukan rata-rata kolom `production_result.achievement`. |
| B3 | Pembulatan | SQL Bagian A: `ROUND()` ke bilangan bulat. API: 2 desimal, bertipe number. | Contoh soal tidak konsisten (Soal 2 memotong 96,76 → 96, Soal 3 membulatkan 96,83 → 97). |
| B4 | Tanggal acuan | Semua pengelompokan tanggal memakai `DATE(production_result.actual_start)` | 466 hasil produksi Shift 3 melewati tengah malam. |
| B5 | WO ↔ hasil | Satu WO maksimal satu hasil produksi, dijaga validasi aplikasi | Dataset tidak punya constraint UNIQUE dan skema tidak boleh diubah. |
| B6 | Pola agregasi | Hasil produksi dan downtime diagregasi per WO lebih dulu, baru di-join ke `work_order` | Mencegah target/qty terhitung ganda. |
| B7 | "production_date" | Dipetakan ke `actual_start` | Kolom `production_date` tidak ada di dataset. |
| B8 | Nama field input | `good_qty`, `reject_qty` (nama kolom DB), bukan `qty_good`, `qty_reject` | Konsisten dengan skema. |

Keputusan tambahan:

| Topik | Keputusan |
|---|---|
| "Hari ini" dashboard | `DATE(MAX(actual_start))` = 2026-12-31, bukan tanggal server, karena data berisi tanggal setelah hari ini. |
| Total WO Soal 1 dan `total_order` endpoint mesin | Menghitung **semua status** (relasi operator–produk dan order terjadwal tetap ada walau WO dibatalkan). |
| `running_order`, `finished_order`, `top_machines` | Seluruh data, bukan hanya tanggal acuan. |
| Ranking Soal 3 | `RANK()` atas achievement yang sudah dibulatkan (nilai sama → ranking sama). |
| Soal 4 | Baris `GRAND TOTAL` ditampilkan. |
| WO OPEN dengan hasil produksi `good_qty` 0 | Tetap ikut dihitung sebagai non-CANCELLED; data tidak diperbaiki. |

Detail lengkap ada di [docs/initiate-file/prd.md](docs/initiate-file/prd.md) §5–§6 dan §10.

---

## Struktur project

```
├── app/
│   ├── Http/Controllers/Api/   # DashboardController, ProductionOrderController, ProductionResultController
│   ├── Http/Requests/          # Validasi query string & body
│   ├── Http/Resources/         # Bentuk JSON production order
│   └── Services/               # Query agregasi & logika bisnis
├── database/dataset.sql        # Dataset asli (tidak diedit)
├── docker/
│   ├── nginx/default.conf
│   └── php/                    # Dockerfile + entrypoint.sh
├── resources/
│   ├── views/dashboard.blade.php
│   └── js/
│       ├── app.js              # mount Vue
│       ├── api/client.js       # fetch /api/dashboard
│       ├── composables/useDashboard.js
│       ├── utils/format.js
│       └── components/         # DashboardPage, KpiCard, TrendChart, StatusChart, TopMachineChart, TopMachineTable
├── routes/api.php, routes/web.php
├── sql/                        # Jawaban Bagian A
├── docs/                       # Dokumentasi teknis, PRD, keputusan
└── docker-compose.yml
```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Port `80` atau `3307` sudah dipakai | Ubah mapping di `docker-compose.yml` (`nginx.ports`, mis. `"8080:80"`; `db.ports`) lalu sesuaikan `APP_URL` di `.env`. |
| Halaman tampil tanpa style / aset 404 | Build ulang aset: `docker compose run --rm node`. |
| Perubahan `.env` tidak berpengaruh | `docker compose exec app php artisan optimize:clear` |
| Data hasil `POST` ingin dibuang | `docker compose down -v && docker compose up -d` |
| `app` langsung berhenti dengan error `no such file or directory` pada `entrypoint.sh` (umumnya di Windows) | Pastikan `docker/php/entrypoint.sh` ber-line ending LF, lalu `docker compose up -d --build`. |
| Tidak bisa mengedit `vendor/`, `node_modules/`, `public/build/`, `storage/` dari host (Linux) | File tersebut dibuat container sebagai `root` / `www-data`. Pakai `sudo` atau `docker compose exec app …`. |
| Dashboard menampilkan "Failed to load dashboard data" | Cek `docker compose ps` dan `docker compose logs app`, lalu klik **Try again**. |

---

## Di luar lingkup

Sesuai soal dan PRD, hal berikut sengaja tidak dibuat:

- Login, autentikasi, dan role-based access (kandidat pengembangan lanjutan).
- Create/update/delete Work Order, termasuk perubahan status WO.
- Perubahan struktur tabel atau perbaikan anomali data awal.
- Automated test.

---

## Dokumentasi lanjutan

- Soal asli: [docs/initiate-file/soal-test.md](docs/initiate-file/soal-test.md)
- PRD (tafsiran + keputusan): [docs/initiate-file/prd.md](docs/initiate-file/prd.md)
- Peta arsitektur: [docs/architecture.md](docs/architecture.md)
- Model data: [docs/data-model.md](docs/data-model.md)
- Dokumentasi per fitur: [docs/features/index.md](docs/features/index.md)
