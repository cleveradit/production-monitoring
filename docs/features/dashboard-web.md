# Dashboard Web

**Status:** Live

## Summary

Halaman `/` (Blade `dashboard`) me-mount aplikasi Vue 3 yang menampilkan 6 KPI, 3 grafik Chart.js, dan tabel Top 10 mesin. Seluruh data berasal dari satu `fetch('/api/dashboard')` per load/refresh; `DashboardPage.vue` adalah satu-satunya pemilik data dan meneruskannya ke komponen lain lewat props. Tersedia state loading (skeleton), error (pesan + "Try again"), dan tombol "Refresh".

## Quick Reference

| Bagian | Komponen | Sumber data |
|---|---|---|
| KPI Total Machine, Running Work Order, Finished Work Order | `KpiCard.vue` | `summary.total_machine`, `running_order`, `finished_order` |
| KPI Achievement, Good Qty, Reject Qty (keterangan "Data date") | `KpiCard.vue` | `summary.achievement`, `today_good`, `today_reject`, `data_date` |
| Line chart Good & Reject 7 hari (dua sumbu Y) | `TrendChart.vue` | `trend_7_days` |
| Donut status Work Order | `StatusChart.vue` | `status_breakdown` |
| Bar horizontal Top 10 mesin (good qty) | `TopMachineChart.vue` | `top_machines` |
| Tabel Top 10 Machine Performance | `TopMachineTable.vue` | `top_machines` |

### Kode

| File | Isi |
|---|---|
| `routes/web.php` | `Route::view('/', 'dashboard')` |
| `resources/views/dashboard.blade.php` | HTML dasar, `#app`, `@vite` |
| `resources/js/app.js` | `createApp(DashboardPage).mount('#app')` |
| `resources/js/api/client.js` | `fetchDashboard()`; lempar `Error` bila `!response.ok` |
| `resources/js/composables/useDashboard.js` | `useDashboard()` → `{ data, loading, error, load }` |
| `resources/js/utils/format.js` | `formatNumber`, `formatPercent`, `formatDate`, `formatShortDate` (locale `id-ID`) |
| `resources/js/components/` | `DashboardPage.vue` + 5 komponen di tabel atas |

### Props komponen

| Komponen | Props |
|---|---|
| `KpiCard.vue` | `label`, `value` (string terformat), `hint?`, `tone?` (`slate`, `blue`, `green`, `amber`, `red`, `indigo`) |
| `TrendChart.vue` | `trend` |
| `StatusChart.vue` | `breakdown` |
| `TopMachineChart.vue` | `machines` |
| `TopMachineTable.vue` | `machines` |

### Layout

| Lebar | KPI | Chart/tabel |
|---|---|---|
| < 640 px | 1 kolom | 1 kolom |
| `sm` (≥ 640 px) / `md` (≥ 768 px) | 2 / 3 kolom | 1 kolom |
| `lg` (≥ 1024 px) | 3 kolom | Trend 2/3 + donut 1/3; bar + tabel 1/2 : 1/2 |
| `xl` (≥ 1280 px) | 6 kolom | sama dengan `lg` |

## Gotchas

- Chart.js di-import tree-shaken: tiap komponen chart memanggil `Chart.register(...)` hanya untuk controller/element/scale yang dipakai. Menambah tipe chart atau plugin (mis. `Filler`) tanpa mendaftarkannya menghasilkan error runtime, bukan error build.
- Instance chart disimpan di variabel biasa (bukan `ref`) agar Chart.js tidak bekerja di atas proxy reaktif Vue; data dibangun ulang sebagai array baru lalu `chart.update()`.
- Saat error, konten diganti panel error sehingga komponen chart di-unmount (`destroy()`) dan dibuat ulang saat "Try again" berhasil.
- `formatDate` mem-parse `YYYY-MM-DD` sebagai UTC dan memformat dengan `timeZone: 'UTC'` agar tanggal tidak bergeser satu hari.
- Bila container `app` berhenti, nginx bisa menahan request sampai timeout proxy (±60 detik, lalu 504) sebelum UI menampilkan error; selama itu tombol menampilkan "Loading…".
- Class Tailwind di file `.vue` hanya ikut ter-build karena `@source '../**/*.vue'` di `resources/css/app.css`.
- Setiap perubahan frontend butuh build ulang: `docker compose run --rm node`.

## Related

- [API Dashboard](api-dashboard.md)
- [PRD §7.6](../initiate-file/prd.md#76-soal-6--dashboard-web)
- [Architecture](../architecture.md)
