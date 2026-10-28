# PRD — Production Monitoring System

**Konteks:** Technical test Software Engineer Staff (Avian Brands), studi kasus PT XYZ Manufacturing.  
**Soal asli:** [soal-test.md](soal-test.md) — jika PRD ini dan soal bertentangan, soal yang menang, lalu PRD diperbaiki.  
**Dataset:** `database/dataset.sql` (database `manufacturing_test`).  
**Audiens:** AI agent dan developer yang mengerjakan project ini.

---

## 1. Latar Belakang dan Tujuan

PT XYZ Manufacturing (pabrik cat) ingin memantau aktivitas produksi: apakah target tercapai, mesin mana yang berkinerja baik atau buruk, bagaimana tren harian, dan berapa Work Order yang sedang berjalan. Rencana produksi dicatat sebagai Work Order (WO), hasil aktualnya dicatat di `production_result`.

Tujuan project:

1. Menjawab 4 soal SQL analitik (Bagian A).
2. Menyediakan REST API Laravel untuk dashboard, detail mesin, daftar production order, dan input hasil produksi (Soal 5).
3. Menyediakan dashboard web Vue + Tailwind yang mengambil data dari satu endpoint (Soal 6).
4. Seluruh aplikasi berjalan dengan satu perintah `docker compose up -d` (Soal 7).

Yang dinilai penguji: ketepatan query, kualitas API, kualitas frontend, dan kemudahan menjalankan aplikasi.

---

## 2. Aturan Mutlak

| # | Aturan | Konsekuensi teknis |
|---|---|---|
| R1 | Struktur tabel dan isi data awal tidak boleh diubah. | Dilarang menambah/mengubah tabel, kolom, view, index, trigger. Dilarang mengedit/menghapus baris yang sudah ada. |
| R2 | Jangan menjalankan `php artisan migrate` (termasuk migration bawaan Laravel). | Hapus atau abaikan migration bawaan. `.env`: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. |
| R3 | Satu-satunya operasi tulis aplikasi adalah `POST /api/production-results` (INSERT ke `production_result`). | Tidak ada update/delete data. Tidak ada perubahan status WO. |
| R4 | Soal 4 wajib Recursive CTE untuk deret tanggal. | Tidak boleh tabel kalender, tidak boleh tanggal hardcode. |
| R5 | Soal 2 wajib CTE; subquery tidak boleh dipakai sebagai pengganti CTE. Soal 3 wajib CTE sebelum window function. | Agregasi dilakukan di CTE bernama. |
| R6 | `today_*` dan `trend_7_days` relatif ke `MAX(actual_start)`, bukan tanggal server. | Jangan memakai `CURDATE()`/`now()` untuk menentukan "hari ini" di dashboard. |
| R7 | Seluruh data dashboard berasal dari satu panggilan `GET /api/dashboard`. | Frontend tidak memanggil endpoint lain untuk KPI, tabel, dan grafik. |
| R8 | Aplikasi jalan dengan `docker compose up -d` tanpa langkah manual tambahan. | Import DB, `composer install`, `APP_KEY`, build aset frontend harus otomatis. |

---

## 3. Pengguna

Soal hanya menyebut dua pengguna. Aplikasi **tidak** memiliki login maupun role.

| Pengguna | Kebutuhan (dari soal) |
|---|---|
| Supervisor | Pencapaian produksi per produk (Soal 2). |
| Management | Performa dan ranking mesin (Soal 3). |

Operator hanya muncul sebagai data (`employee`), bukan pengguna aplikasi.

---

## 4. Stack dan Lingkungan

| Komponen | Versi / pilihan |
|---|---|
| PHP | 8.3 (FPM) |
| Framework | Laravel 12 |
| Database | MySQL 8.4 LTS |
| Web server | Nginx (alpine) |
| Frontend | Vue 3 via Vite (`laravel-vite-plugin`, `@vitejs/plugin-vue`), di-mount di satu halaman Blade |
| CSS | Tailwind CSS v4 (plugin `@tailwindcss/vite`), di-build — bukan CDN |
| Chart | Chart.js |
| Orkestrasi | Docker Compose |
| Timezone aplikasi | `Asia/Jakarta` |

**Deviasi dari soal:** Soal 7 menyebut MariaDB sebagai container minimal; project ini memakai **MySQL 8.4** (soal Bagian A/B membolehkan MySQL/MariaDB). Deviasi ini wajib ditulis di README beserta alasannya.

---

## 5. Data

### 5.1 Tabel yang dipakai

| Tabel | Isi | Baris |
|---|---|---:|
| `work_order` | Perintah produksi: produk, mesin, operator, shift, target, jadwal, status. Tabel pusat. | 1.500 |
| `production_result` | Hasil aktual per WO: `actual_start`, `actual_finish`, `runtime_minutes`, `good_qty`, `reject_qty`, `achievement`. | 1.474 |
| `product` | Master produk (`product_code`, `product_name`, `category`, `target_min`, `target_max`). | 43 |
| `machine` | Master mesin (`machine_code`, `machine_name`, `production_line`). | 24 |
| `employee` | Master operator (`employee_no`, `full_name`). | 60 |
| `downtime` | Waktu berhenti mesin per WO (`duration_minutes`). Bisa banyak baris per WO. | 1.258 |

Tidak dipakai soal: `maintenance`, `inventory_transaction`.

### 5.2 Relasi

- `work_order.product_code` → `product`, `work_order.machine_code` → `machine`, `work_order.employee_no` → `employee`.
- `production_result.wo_number` → `work_order` (tanpa constraint UNIQUE).
- `downtime.wo_number` → `work_order`.
- Many-to-many Product ↔ Operator terjadi **melalui `work_order`** (tidak ada pivot table).

### 5.3 Temuan Data Penting

| # | Temuan | Dampak |
|---|---|---|
| D1 | Status WO: FINISHED 1.243, RUNNING 157, OPEN 74, CANCELLED 26. | — |
| D2 | Semua WO CANCELLED tidak punya `production_result` tetapi tetap punya `target_qty`. | Jika CANCELLED ikut dihitung, target membesar dan achievement turun. |
| D3 | WO OPEN punya `production_result` dengan `good_qty` 0. | Ikut dihitung sebagai bagian dari non-CANCELLED. Data tidak diperbaiki (R1). |
| D4 | Setiap WO punya maksimal satu `production_result`. | Dasar keputusan B5. |
| D5 | Semua WO RUNNING sudah punya `production_result`. | Dengan data awal, `POST /api/production-results` selalu ditolak (lihat §7.5.4). |
| D6 | Rentang `actual_start`: 2026-01-02 s.d. 2026-12-31 (melewati tanggal hari ini). | Alasan R6. "Hari ini" dashboard = 2026-12-31. |
| D7 | Ada 7 tanggal tanpa produksi: 2026-04-19, 04-20, 04-28, 07-20, 09-16, 09-21, 12-27. | 2026-12-27 masuk `trend_7_days` dan harus tampil 0. Juga harus muncul di Soal 4. |
| D8 | 466 hasil produksi melewati tengah malam (Shift 3: 23:00–07:00). | Tanggal acuan harus konsisten (B4). |
| D9 | Kolom `production_result.achievement` = `good_qty / target_qty × 100` (2 desimal) untuk semua baris. | Agregat dihitung ulang dari SUM, **bukan** rata-rata kolom ini. |
| D10 | Contoh Soal 2 (Total WO 38 untuk Enamel Black yang punya 40 WO, 2 CANCELLED) dan contoh Soal 3 hanya cocok jika CANCELLED dikecualikan. | Dasar keputusan B1. |
| D11 | Contoh Soal 1 baris 2–3 tidak cocok dengan data (mis. Wall Sealer: data 40 WO / 31 operator, contoh 74 / 26). | Contoh Soal 1 hanya ilustrasi format. |
| D12 | Pembulatan contoh tidak konsisten: Soal 2 memotong (96,76 → 96), Soal 3 membulatkan (96,83 → 97). | Dasar keputusan B3. |
| D13 | Tidak ada constraint UNIQUE di seluruh dataset. | Batasan 1 WO 1 hasil dijaga di aplikasi (B5). |

---

## 6. Keputusan Bisnis (Global)

| # | Topik | Keputusan |
|---|---|---|
| B1 | Status WO pada metrik produksi | Target, good, reject, achievement, dan "Total WO" pada laporan produksi **mengecualikan CANCELLED** (D10). `status_breakdown` tetap menampilkan keempat status. Lihat §10 untuk Soal 1 dan `total_order`. |
| B2 | Rumus achievement agregat | `SUM(good_qty) / SUM(target_qty) × 100`. Jika `SUM(target_qty)` = 0 maka achievement = 0. |
| B3 | Pembulatan | SQL Bagian A: `ROUND()` ke bilangan bulat. API: `ROUND(…, 2)` (2 desimal, tipe number di JSON). Ditulis sebagai asumsi di README. |
| B4 | Tanggal acuan produksi | `DATE(production_result.actual_start)` untuk semua pengelompokan tanggal, "hari ini", tren, dan Soal 4. |
| B5 | Relasi WO – hasil | 1 WO maksimal 1 `production_result`, dijaga oleh validasi aplikasi. |
| B6 | Pola agregasi | Hasil produksi diagregasi per WO lebih dulu (CTE / subquery terstruktur di API), baru di-join ke `work_order`, agar target tidak terhitung ganda. |
| B7 | Field "production_date" di soal | Dipetakan ke `actual_start`. |
| B8 | Nama field input qty | Request memakai nama kolom DB: `good_qty`, `reject_qty` (soal menulis `qty_good`/`qty_reject`). Pemetaan ditulis di README. |

---

## 7. Kebutuhan Fungsional

### 7.1 Soal 1 — Many-to-Many (SQL)

**Lokasi:** `sql/soal-1-many-to-many.sql`

| Kolom | Definisi |
|---|---|
| Product | `product.product_name` |
| Total WO | Jumlah WO produk tersebut (aturan status: §10 K1) |
| Total Operator | `COUNT(DISTINCT employee_no)` |
| Nama Operator | `GROUP_CONCAT(DISTINCT full_name ORDER BY full_name SEPARATOR ', ')` |

- Urutan: Total Operator DESC, lalu Total WO DESC, lalu Product ASC.
- Gotcha: `group_concat_max_len` default 1024 byte; daftar nama saat ini cukup (±34 nama), tetapi set `SET SESSION group_concat_max_len` bila perlu.

### 7.2 Soal 2 — CTE Dasar (SQL)

**Lokasi:** `sql/soal-2-cte-product-achievement.sql`

| Kolom | Definisi |
|---|---|
| Product | `product.product_name` |
| Total WO | Jumlah WO non-CANCELLED |
| Target | `SUM(target_qty)` WO non-CANCELLED |
| Good Qty | `SUM(good_qty)` |
| Reject Qty | `SUM(reject_qty)` |
| Achievement (%) | B2, dibulatkan B3 |

- Wajib `WITH`; tidak ada subquery di `FROM`/`WHERE` sebagai pengganti CTE.
- Urutan: Achievement DESC (gunakan nilai presisi penuh untuk urutan), lalu Product ASC.
- Verifikasi: baris teratas harus Avian Synthetic Enamel Black 5 Kg — 38 / 87009 / 84416 / 679 / 97.

### 7.3 Soal 3 — CTE + Window Function (SQL)

**Lokasi:** `sql/soal-3-machine-ranking.sql`

| Kolom | Definisi |
|---|---|
| Machine | `machine.machine_name` |
| Total Good | `SUM(good_qty)` WO non-CANCELLED |
| Total Reject | `SUM(reject_qty)` |
| Achievement | B2, dibulatkan B3 |
| Ranking | `RANK() OVER (ORDER BY achievement DESC)` |

- Agregasi di CTE, window function di query setelahnya.
- Nilai yang di-ranking: achievement yang sudah dibulatkan (nilai yang ditampilkan), agar nilai tampil sama → ranking sama. Lihat §10 K3.
- Verifikasi: 3 teratas = Labelling Machine 02 (176666 / 1572 / 97 / 1), Mixing Tank 01 (184727 / 1471 / 95 / 2), Paint Disperser 02 (164486 / 1402 / 94 / 3).

### 7.4 Soal 4 — Recursive CTE + Rollup (SQL)

**Lokasi:** `sql/soal-4-recursive-trend.sql`

Alur query:

1. CTE rekursif menghasilkan deret tanggal dari `DATE(MIN(actual_start))` s.d. `DATE(MAX(actual_start))` (diambil dari data, bukan hardcode).
2. CTE agregasi harian dari `production_result` + `work_order` (non-CANCELLED, B4).
3. `LEFT JOIN` deret tanggal ke agregasi harian; nilai kosong → 0.
4. `GROUP BY tahun, bulan, tanggal WITH ROLLUP`.
5. Kolom `level` dari `GROUPING()`: `DATE`, `MONTH`, `YEAR`, `GRAND TOTAL`.

| Kolom | Definisi |
|---|---|
| level | `DATE` / `MONTH` / `YEAR` / `GRAND TOTAL` |
| year | Tahun |
| month | Bulan (1–12) |
| date | Tanggal (`YYYY-MM-DD`), NULL pada baris subtotal |
| total_good | `SUM(good_qty)` |
| total_reject | `SUM(reject_qty)` |
| achievement | B2 terhadap target pada level tersebut, dibulatkan B3; 0 jika target 0 |

- Urutan kronologis; baris subtotal muncul setelah anak-anaknya (perilaku alami ROLLUP).
- Gotcha: `cte_max_recursion_depth` default 1000; rentang data 364 hari aman.
- Verifikasi: 2026-01-02 → 5223 / 17 / 97; 2026-01-03 → 14866 / 111 / 100; 2026-01-04 → 6363 / 52 / 86; 2026-12-27 muncul dengan 0 / 0 / 0.

### 7.5 Soal 5 — REST API

Semua response JSON. Error validasi memakai format standar Laravel (HTTP 422). Resource tidak ditemukan → HTTP 404 JSON.

#### 7.5.1 `GET /api/dashboard`

Definisi tanggal acuan: `D = DATE(MAX(production_result.actual_start))` (saat ini 2026-12-31).

| Field | Definisi |
|---|---|
| `summary.total_machine` | `COUNT(*)` tabel `machine` |
| `summary.running_order` | Jumlah WO berstatus RUNNING (seluruh data) |
| `summary.finished_order` | Jumlah WO berstatus FINISHED (seluruh data) |
| `summary.today_target` | `SUM(target_qty)` WO non-CANCELLED yang hasilnya `DATE(actual_start) = D` |
| `summary.today_good` | `SUM(good_qty)` hasil dengan `DATE(actual_start) = D` |
| `summary.today_reject` | `SUM(reject_qty)` hasil dengan `DATE(actual_start) = D` |
| `summary.achievement` | `today_good / today_target × 100`, 2 desimal |
| `summary.data_date` | `D` (`YYYY-MM-DD`) — field tambahan agar UI bisa menampilkan tanggal acuan |
| `trend_7_days[]` | Tepat 7 elemen, `D-6` s.d. `D` urut naik: `{date, good_qty, reject_qty, achievement}`; tanggal tanpa data → 0 |
| `status_breakdown[]` | Selalu 4 elemen urut RUNNING, FINISHED, OPEN, CANCELLED: `{status, total}`; status tanpa WO → 0 |
| `top_machines[]` | Maks 10 mesin, urut `good_qty` DESC (seluruh data, non-CANCELLED): `{machine_code, machine_name, good_qty, achievement}` |

#### 7.5.2 `GET /api/dashboard/machine/{id}`

`{id}` = `machine_code` (mis. `MXA01`). Tidak ditemukan → 404.

| Field | Definisi |
|---|---|
| `machine_code` | Kode mesin (field tambahan) |
| `machine_name` | Nama mesin |
| `total_order` | Jumlah WO mesin tersebut (aturan status: §10 K1) |
| `good_qty` | `SUM(good_qty)` WO non-CANCELLED |
| `reject_qty` | `SUM(reject_qty)` WO non-CANCELLED |
| `downtime_minutes` | `SUM(downtime.duration_minutes)` untuk WO mesin tersebut |
| `achievement` | B2, 2 desimal |

Gotcha: hitung downtime di agregasi terpisah, jangan join `downtime` bersamaan dengan `production_result` (baris downtime ganda akan menggandakan good/reject).

#### 7.5.3 `GET /api/production-orders`

| Parameter | Aturan |
|---|---|
| `search` | LIKE pada `wo_number`, `product_name`, `machine_name`, `full_name` operator |
| `product_code` | Filter persis |
| `machine_code` | Filter persis |
| `status` | Salah satu: OPEN, RUNNING, FINISHED, CANCELLED |
| `date_from`, `date_to` | Format `Y-m-d`, filter `DATE(plan_start)`; `date_to ≥ date_from` |
| `sort_by` | Whitelist: `wo_number`, `plan_start`, `plan_finish`, `target_qty`, `status`, `product_name`, `machine_name`. Default `plan_start` |
| `sort_dir` | `asc` / `desc`. Default `desc` |
| `per_page` | Default 15, maks 100 |
| `page` | Pagination Laravel |

Parameter tidak valid → 422.

Item response: `wo_number`, `product {code, name}`, `machine {code, name}`, `operator {employee_no, name}`, `shift`, `target_qty`, `plan_start`, `plan_finish`, `status`, `result {actual_start, actual_finish, good_qty, reject_qty, achievement}` (`null` jika belum ada hasil). Struktur pagination mengikuti paginator Laravel (`data`, `links`, `meta`).

#### 7.5.4 `POST /api/production-results`

Request body:

| Field | Validasi |
|---|---|
| `wo_number` | required, ada di `work_order` |
| `actual_start` | required, `Y-m-d H:i:s`, ≤ waktu sekarang (aturan "production_date tidak boleh melebihi hari ini", B7) |
| `actual_finish` | required, `Y-m-d H:i:s`, setelah `actual_start`, ≤ waktu sekarang |
| `good_qty` | required, integer, ≥ 0 |
| `reject_qty` | required, integer, ≥ 0 |

Aturan bisnis (setelah validasi format):

1. Status WO harus RUNNING → selain itu 422.
2. WO belum memiliki `production_result` (B5) → jika sudah ada, 422 dengan pesan jelas.

Nilai yang dihitung server:

- `runtime_minutes` = selisih menit `actual_finish - actual_start`.
- `achievement` = `ROUND(good_qty / work_order.target_qty × 100, 2)`.

Response sukses: HTTP 201 dengan data baris yang dibuat. Status WO **tidak** diubah (R3).

**Catatan perilaku:** dengan data awal, semua WO RUNNING sudah punya hasil (D5), sehingga endpoint ini selalu menghasilkan 422. Ini perilaku yang disengaja dan wajib dijelaskan di README beserta contoh request/response untuk tiap kasus penolakan.

### 7.6 Soal 6 — Dashboard Web

**Route:** `GET /` → view Blade yang me-mount aplikasi Vue.

| Bagian | Isi | Sumber data |
|---|---|---|
| KPI | Total Machine, Running WO, Finished WO, Achievement, Good Qty, Reject Qty | `summary` |
| Tabel | Top 10 Machine Performance (kode, nama, good, achievement) | `top_machines` |
| Line chart | Trend 7 hari (good & reject per tanggal) | `trend_7_days` |
| Donut chart | Status Work Order | `status_breakdown` |
| Bar chart | Top 10 mesin berdasarkan good qty | `top_machines` |

Kebutuhan:

- Satu panggilan `fetch` ke `/api/dashboard`; data dibagikan ke komponen lewat props (R7).
- State loading, error (dengan tombol coba lagi), dan tombol refresh.
- Tampilkan tanggal acuan data (`summary.data_date`) di dekat KPI "today".
- Responsif: mobile (1 kolom), tablet, desktop (grid KPI dan chart).
- Format angka ribuan dan persen.

Struktur kode yang diharapkan:

```
resources/js/
├── app.js                      # mount Vue
├── api/client.js               # fungsi fetch ke /api/dashboard
├── composables/useDashboard.js # state data/loading/error
└── components/
    ├── DashboardPage.vue
    ├── KpiCard.vue
    ├── TrendChart.vue
    ├── StatusChart.vue
    ├── TopMachineChart.vue
    └── TopMachineTable.vue
```

### 7.7 Soal 7 — Docker

| Service | Image / build | Peran |
|---|---|---|
| `app` | Build `docker/php/Dockerfile` (php:8.3-fpm + `pdo_mysql`, `zip`, `bcmath`, Composer) | Menjalankan Laravel |
| `nginx` | `nginx:1.27-alpine` | Web server, port host `80` |
| `db` | `mysql:8.4` | Database `manufacturing_test`, port host `3307` |
| `node` | `node:22-alpine`, sekali jalan | `npm ci && npm run build` ke folder project |

Kebutuhan:

- `database/dataset.sql` di-mount ke `/docker-entrypoint-initdb.d/` → import otomatis saat volume DB masih kosong.
- `app` menunggu `db` healthy (`mysqladmin ping`) dan `node` selesai (`service_completed_successfully`).
- Entrypoint `app`: salin `.env.example` → `.env` bila belum ada, `composer install` bila `vendor/` belum ada, `key:generate` bila `APP_KEY` kosong, `chown` `storage` dan `bootstrap/cache` ke `www-data`. Tanpa `migrate` (R2).
- `.env.example` sudah berisi konfigurasi Docker: `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=manufacturing_test`, `APP_URL=http://localhost`, `APP_TIMEZONE=Asia/Jakarta`, plus setting R2.

Gotcha:

- Bind mount `./:/var/www/html` menutupi file hasil build di dalam image; karena itu aset Vite dibangun oleh service `node` ke folder yang di-mount.
- `dataset.sql` menjalankan `DROP DATABASE` + `CREATE DATABASE`; grant user aplikasi pada `manufacturing_test` tetap berlaku.
- Import hanya terjadi sekali. Import ulang: `docker compose down -v` lalu `up -d`.
- `entrypoint.sh` harus ber-line ending LF.

README wajib memuat: cara menjalankan, konfigurasi `.env`, langkah import database, URL aplikasi dan daftar endpoint, deviasi MySQL vs MariaDB, asumsi bisnis (B1–B8), dan perilaku POST (§7.5.4).

---

## 8. Di Luar Lingkup

1. Login, autentikasi, RBAC (dicatat di README sebagai pengembangan lanjutan).
2. Create/update/delete Work Order, termasuk perubahan status WO.
3. Automated test.
4. Perubahan struktur atau isi data awal (R1), termasuk memperbaiki anomali data (D3).
5. Pemakaian tabel `maintenance` dan `inventory_transaction`.

---

## 9. Urutan Pengerjaan

| Milestone | Isi | Selesai jika |
|---|---|---|
| M0 — Inisialisasi | Repo dari template, isi `ai-context.md` (stack, mandat, aturan R1–R8, dev env), `docs/initiate-file/`, salin `dataset.sql` ke `database/`. | Dokumen inti terisi dan ter-commit. |
| M1 — Docker & skeleton | Laravel 12, Dockerfile PHP, Nginx, MySQL, service Node, entrypoint, `.env.example`. | `docker compose up -d` dari kondisi bersih → `http://localhost` terbuka dan `DB::table('work_order')->count()` = 1500. |
| M2 — Bagian A | Empat file di `sql/`. | Tiap query jalan di MySQL dan lolos verifikasi di §7.1–7.4. |
| M3 — API dashboard | `GET /api/dashboard`, `GET /api/dashboard/machine/{id}`. | Response sesuai §7.5.1–7.5.2; `trend_7_days` berisi 7 elemen termasuk 2026-12-27 bernilai 0. |
| M4 — API production orders | `GET /api/production-orders`, `POST /api/production-results`. | Semua parameter §7.5.3 bekerja; POST menolak tiap kasus §7.5.4 dengan 422. |
| M5 — Dashboard Vue | Halaman `/` sesuai §7.6. | KPI, tabel, 3 grafik tampil dari satu panggilan API; responsif di mobile dan desktop. |
| M6 — README & uji bersih | README sesuai §7.7; uji `docker compose down -v` → `up -d`. | Aplikasi berjalan tanpa langkah manual. |

---

## 10. Keputusan Konfirmasi (K1–K5)

Seluruh default di bawah sudah **dikonfirmasi final** oleh pemilik project. Tabel ini adalah catatan resminya (tidak diduplikasi ke decision log).

| # | Pertanyaan | Keputusan final |
|---|---|---|
| K1 | "Total WO" Soal 1 dan `total_order` endpoint mesin: semua status atau tanpa CANCELLED? Kesepakatan awal "semua status" berdasar contoh Soal 1, tetapi produk di contoh itu tidak punya WO CANCELLED sehingga contoh tidak membuktikan apa pun; Soal 2 terbukti mengecualikan CANCELLED (D10). | Soal 1: **semua status** (relasi operator–produk tetap ada walau WO batal). `total_order`: **semua status** (jumlah order yang pernah dijadwalkan ke mesin). |
| K2 | `running_order` dan `finished_order`: seluruh data atau hanya tanggal `D`? | Seluruh data, konsisten dengan `status_breakdown`. |
| K3 | Ranking Soal 3: `RANK()` (1,1,3) atau `DENSE_RANK()` (1,1,2); berbasis nilai dibulatkan atau presisi penuh? | `RANK()` berbasis nilai yang dibulatkan. |
| K4 | `top_machines`: seluruh data atau hanya tanggal `D`? | Seluruh data. |
| K5 | Baris GRAND TOTAL dari ROLLUP Soal 4 ditampilkan atau dibuang? | Ditampilkan dengan `level = GRAND TOTAL`. |

---

## 11. Glosarium

| Istilah | Arti |
|---|---|
| Work Order (WO) / Production Order / Order | Perintah produksi; satu baris `work_order`. |
| Target | `target_qty` — jumlah yang direncanakan. |
| Good / Reject | Hasil lolos kualitas / cacat. |
| Achievement | Persentase good terhadap target. |
| Downtime | Waktu mesin berhenti saat WO berjalan. |
| Shift | Shift 1 07:00–15:00, Shift 2 15:00–23:00, Shift 3 23:00–07:00. |
| D (tanggal acuan) | `DATE(MAX(actual_start))` — "hari ini" versi data. |
