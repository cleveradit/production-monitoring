# Architecture Map

> **Status:** skeleton Laravel 12 + Docker sudah ada (M1). Baris bertanda *(rencana)* adalah target dari [PRD](initiate-file/prd.md) §7; perbarui dan verifikasi terhadap kode saat milestone terkait selesai.

## Summary

Production Monitoring System untuk PT XYZ Manufacturing: kumpulan query SQL analitik (Bagian A), REST API Laravel 12 yang membaca dataset produksi, dan dashboard Vue 3 + Tailwind yang mengambil seluruh datanya dari `GET /api/dashboard`. Semua berjalan di Docker Compose (PHP-FPM, Nginx, MySQL 8.4, Node untuk build aset). Aplikasi read-only kecuali satu endpoint INSERT `production_result`.

## Components

| Component | Responsibility | Code location | Dependencies |
|---|---|---|---|
| SQL Bagian A | Jawaban Soal 1–4 | `sql/soal-1-many-to-many.sql`, `sql/soal-2-cte-product-achievement.sql`, `sql/soal-3-machine-ranking.sql`, `sql/soal-4-recursive-trend.sql` | MySQL |
| Dashboard API | `GET /api/dashboard`, `GET /api/dashboard/machine/{id}` | `routes/api.php`, `app/Http/Controllers/Api/DashboardController.php`, `app/Services/DashboardService.php` | MySQL |
| Production Order API | `GET /api/production-orders`, `POST /api/production-results` | `routes/api.php`, `app/Http/Controllers/Api/ProductionOrderController.php`, `app/Http/Controllers/Api/ProductionResultController.php`, `app/Http/Requests/`, `app/Http/Resources/ProductionOrderResource.php`, `app/Services/ProductionOrderService.php`, `app/Services/ProductionResultService.php` | MySQL |
| Dashboard Web *(rencana)* | Halaman `/` (Blade) yang me-mount Vue | `resources/views/`, `resources/js/` | Dashboard API |
| Docker | Orkestrasi + bootstrap otomatis (service `app`, `nginx`, `db`, `node`) | `docker-compose.yml`, `docker/php/Dockerfile`, `docker/php/entrypoint.sh`, `docker/nginx/default.conf` | — |
| Laravel skeleton | Framework, konfigurasi (`.env.example`, timezone `Asia/Jakarta`), tanpa migration; routing API + render error JSON untuk `api/*` | `bootstrap/app.php`, `config/`, `routes/web.php` | MySQL |

## Main flows

1. **Dashboard:** browser → Nginx `:80` → `/` (Blade + bundle Vite) → Vue `fetch('/api/dashboard')` → controller → query agregasi → JSON → KPI, tabel, 3 chart.
2. **Input hasil produksi:** `POST /api/production-results` → validasi format → transaksi + lock baris WO → cek WO `RUNNING` dan belum punya hasil → hitung `runtime_minutes` & `achievement` → INSERT → 201.
3. **Bootstrap container:** `db` import `dataset.sql` (volume kosong) → `node` build aset → `app` entrypoint (`.env`, `composer install`, `key:generate`, permission) → `nginx` siap.

## External integrations

None.
