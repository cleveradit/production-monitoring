# Implementation Plan: TICKET-005 (Dashboard Web Vue)

**Ticket:** `TICKET-005`  
**Status:** `DONE`  
**Target Audience:** AI Developer Agents  
**Depends On:** `TICKET-003` (`GET /api/dashboard`)

---

## 1. Business Decision Snapshot (Approved)

| Item | Approved Value |
|---|---|
| Milestone | M5 — PRD §9, spesifikasi PRD §7.6 |
| Route | `GET /` → view Blade `dashboard` yang me-mount Vue di `#app` |
| Sumber data | Satu `fetch('/api/dashboard')` per load/refresh; data dibagi ke komponen lewat props (R7) |
| Paket baru | `vue@^3.5.44`, `@vitejs/plugin-vue@^6.0.10`, `chart.js@^4.6.1` (Chart.js langsung, tanpa wrapper `vue-chartjs`) |
| Paket dihapus | `axios` + `resources/js/bootstrap.js` (tidak dipakai; HTTP memakai `fetch`) |
| Bahasa UI | Label English mengikuti nama KPI di soal (Total Machine, Running Work Order, …) |
| Format angka | `Intl.NumberFormat('id-ID')`: ribuan `7.811`, persen `91,12%`; tanggal `31 Des 2026` |
| KPI "today" | Achievement, Good Qty, Reject Qty diberi keterangan tanggal acuan `summary.data_date` (bukan tanggal hari ini) |
| Line chart | Good dan Reject per tanggal, dua sumbu Y (kiri Good, kanan Reject) karena skala reject ±1% good |
| Donut chart | `status_breakdown`, warna tetap per status (RUNNING biru, FINISHED hijau, OPEN kuning, CANCELLED abu) |
| Bar chart | Horizontal, `top_machines` berdasarkan `good_qty` |
| Tabel | Rank, kode, nama mesin, good qty, achievement (dengan bar progres) |
| State | Loading (skeleton), error (pesan + tombol "Try again"), tombol "Refresh" (menonaktifkan diri saat loading) |
| Layout | Mobile 1 kolom; tablet KPI 2–3 kolom; desktop KPI 6 kolom, chart grid 2–3 kolom |
| Font | System font stack (tanpa request font eksternal) |
| Tema | Light only |

---

## 2. Objective

Menyediakan halaman dashboard yang modern dan responsif di `http://localhost` yang menampilkan 6 KPI, tabel Top 10 mesin, dan 3 grafik, seluruhnya dari satu response `GET /api/dashboard`, dengan state loading/error dan tombol refresh.

---

## 3. Non-Negotiable Technical Contract

1. File: `package.json` / `package-lock.json`
   - Tambah `vue`, `chart.js` (dependencies) dan `@vitejs/plugin-vue` (devDependencies); hapus `axios`. Lockfile diperbarui agar `npm ci` di service `node` berhasil.
2. File: `vite.config.js`
   - Tambah plugin `vue()` (dengan `template.transformAssetUrls` `base: null`, `includeAbsolute: false`).
3. File: `resources/css/app.css`
   - Tambah `@source '../**/*.vue';`; hapus `@theme` font Instrument Sans.
4. File: `resources/views/dashboard.blade.php`
   - HTML dasar, `<div id="app"></div>`, `@vite(['resources/css/app.css', 'resources/js/app.js'])`, `<title>Production Monitoring</title>`.
   - Hapus `resources/views/welcome.blade.php`.
5. File: `routes/web.php`
   - `Route::view('/', 'dashboard');`
6. File: `resources/js/app.js`
   - `createApp(DashboardPage).mount('#app')`.
7. File: `resources/js/api/client.js`
   - `export async function fetchDashboard(): Promise<object>` — `fetch('/api/dashboard', { headers: { Accept: 'application/json' } })`; lempar `Error` bila `!response.ok`.
8. File: `resources/js/composables/useDashboard.js`
   - `export function useDashboard()` → `{ data, loading, error, load }` (`ref`); `load()` dipanggil saat mount dan oleh tombol refresh/retry.
9. File: `resources/js/utils/format.js`
   - `formatNumber(value)`, `formatPercent(value)`, `formatDate(isoDate)`.
10. File: `resources/js/components/`
    - `DashboardPage.vue` — memanggil `useDashboard()`, satu-satunya pemilik data; meneruskan props.
    - `KpiCard.vue` — props `label`, `value`, `hint?`, `tone?`.
    - `TrendChart.vue` — props `trend` (array `trend_7_days`).
    - `StatusChart.vue` — props `breakdown` (array `status_breakdown`).
    - `TopMachineChart.vue` — props `machines` (array `top_machines`).
    - `TopMachineTable.vue` — props `machines`.
    - Komponen chart memakai Chart.js tree-shaken (`Chart.register(...)` hanya controller/element/scale yang dipakai), membuat chart di `onMounted`, memperbarui data via `watch` + `chart.update()`, dan `destroy()` di `onBeforeUnmount`. Canvas diberi `role="img"` dan `aria-label`.

---

## 4. Scope of Changes

### A. Frontend

1. Paket, konfigurasi Vite & Tailwind, Blade view, route.
2. `api/client.js`, `composables/useDashboard.js`, `utils/format.js`, 6 komponen.

### B. Docs

1. `docs/features/dashboard-web.md` + registrasi di `docs/features/index.md`.
2. `docs/architecture.md`: lokasi kode Dashboard Web, hapus *(rencana)*.
3. Hapus item M5 dari `docs/backlog.md`.

---

## 5. Acceptance Test Matrix

| Case | Input | Expected Result | Status |
|---|---|---|---|
| Build | `docker compose run --rm node` | `npm ci` + `vite build` sukses tanpa error | `[x]` |
| Halaman | `GET /` | 200; HTML berisi `#app` dan aset `/build/assets/*.js` + `*.css` (200) | `[x]` |
| Satu panggilan API | Buka `/` di browser, panel Network | Tepat 1 request XHR/fetch ke `/api/dashboard` per load | `[x]` |
| KPI | Render | Total Machine 24, Running WO 157, Finished WO 1.243, Achievement 91,12%, Good Qty 7.811, Reject Qty 26, keterangan tanggal 31 Des 2026 | `[x]` |
| Tabel | Render | 10 baris, DSP02 teratas, LBL02 terbawah | `[x]` |
| Boundary — trend dengan 0 | Line chart | 7 titik 25–31 Des, 27 Des bernilai 0 (titik tetap ada) | `[x]` |
| Donut & bar | Render | Donut 4 status; bar 10 mesin urut good qty | `[x]` |
| Failure — API gagal | Hentikan container `app` lalu klik Refresh | Pesan error + tombol "Try again"; setelah `app` jalan lagi, klik → data tampil | `[x]` |
| Responsif | Lebar 375 px, 768 px, 1280 px | Tidak ada scroll horizontal; KPI 1 → 2/3 → 6 kolom | `[x]` |
| Tailwind dari build | HTML | Tidak ada `<script src="cdn.tailwindcss.com">`; CSS hasil build berisi class yang dipakai komponen `.vue` | `[x]` |
| Bersih | `docker compose down -v && docker compose up -d` | Halaman tampil lengkap tanpa langkah manual | `[x]` |

Verifikasi visual dilakukan dengan screenshot headless browser (Playwright/Chromium) bila tersedia di mesin; bila tidak, dengan pengecekan manual oleh user.

**Hasil verifikasi:** Playwright (Chromium headless) di 375/768/1280 px: 1 request `/api/dashboard` per load, tanpa console error, tanpa scroll horizontal, KPI 1/3/6 kolom, 3 canvas, tabel 10 baris DSP02→LBL02. Failure case: `docker compose stop app` → Refresh menampilkan panel error (nginx memberi 504 setelah ±60 detik) → `start app` → "Try again" memuat data kembali. Uji bersih `down -v && up -d` lolos.

**Catatan implementasi:** tambahan `formatShortDate()` di `utils/format.js` untuk label sumbu X line chart (`25 Des`).

---

## 6. Verification Commands

1. `docker compose run --rm node`
2. `curl -s http://localhost/ | grep -o '/build/assets/[^"]*'`
3. Screenshot headless di 375/768/1280 px (Playwright) + cek jumlah request `/api/dashboard`
4. `docker compose down -v && docker compose up -d`

Expected:

1. Build sukses, halaman dan aset 200, tampilan sesuai Acceptance Test Matrix.

---

## 7. Out of Scope

1. Halaman list production order / form input hasil produksi (tidak diminta soal).
2. Klik mesin → detail via `/api/dashboard/machine/{id}` (melanggar "satu panggilan API" R7).
3. Dark mode, i18n, auto-refresh berkala.
4. Automated test (PRD §8).

---

## 8. Completion Checklist

- [x] Section 1 fully filled and status set correctly.
- [x] All Non-Negotiable Technical Contract items implemented.
- [x] Acceptance Test Matrix completed.
- [x] Verification commands executed successfully.
- [x] No out-of-scope changes introduced.
