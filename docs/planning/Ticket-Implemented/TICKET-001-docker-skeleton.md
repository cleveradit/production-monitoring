# Implementation Plan: TICKET-001 (Docker & Skeleton Laravel)

**Ticket:** `TICKET-001`  
**Status:** `DONE`  
**Target Audience:** AI Developer Agents  
**Depends On:** `None`

---

## 1. Business Decision Snapshot (Approved)

| Item | Approved Value |
|---|---|
| Milestone | M1 — PRD §9 |
| Versi Laravel | `laravel/laravel ^12.0`, dependency di-lock untuk platform PHP `8.3` (`config.platform.php`) |
| Cara membuat skeleton | `composer create-project --no-scripts --no-install` (mencegah `migrate` otomatis dari post-create script), lock dibuat dengan `composer update --no-install` |
| Migration bawaan | Dihapus seluruhnya (R2) |
| Database container | `mysql:8.4`, user aplikasi `app` / `secret`, root `root` |
| Port host | Nginx `8080`, MySQL `3307` |
| Healthcheck DB | `mysqladmin ping -h 127.0.0.1` (TCP, agar tidak "healthy" saat server sementara init masih mengimport) |
| API routing & Vue | Bukan bagian tiket ini (M3–M5) |
| Approval | User meminta "buatkan TICKET-001 untuk M1 dan langsung implementasikan" |

---

## 2. Objective

Menyediakan skeleton Laravel 12 yang berjalan penuh lewat `docker compose up -d` dari kondisi bersih: database `manufacturing_test` ter-import otomatis dari `database/dataset.sql`, dependency PHP terpasang, `APP_KEY` dibuat, aset Vite di-build, dan halaman `http://localhost:8080` terbuka — tanpa langkah manual dan tanpa `migrate`.

---

## 3. Non-Negotiable Technical Contract

1. File: `docker-compose.yml`
   - Service `app` (build `docker/php`), `nginx` (`nginx:1.27-alpine`, `8080:80`), `db` (`mysql:8.4`, `3307:3306`), `node` (`node:22-alpine`, `npm ci && npm run build`, sekali jalan).
   - `app.depends_on`: `db` → `service_healthy`, `node` → `service_completed_successfully`.
   - `db` mount `./database/dataset.sql:/docker-entrypoint-initdb.d/01-dataset.sql:ro` dan named volume `db-data`.
   - Bind mount `./:/var/www/html` pada `app` dan `nginx`.
2. File: `docker/php/Dockerfile`
   - Base `php:8.3-fpm`; ekstensi `pdo_mysql`, `zip`, `bcmath`; Composer dari `composer:2`; entrypoint `docker/php/entrypoint.sh`.
3. File: `docker/php/entrypoint.sh` (LF)
   - Salin `.env.example` → `.env` bila belum ada; `composer install` bila `vendor/` belum ada; `php artisan key:generate --force` bila `APP_KEY` kosong; `chown -R www-data` pada `storage` dan `bootstrap/cache`; `exec php-fpm`. Tidak ada `migrate`.
4. File: `docker/nginx/default.conf`
   - Root `/var/www/html/public`, `try_files` ke `index.php`, `fastcgi_pass app:9000`.
5. File: `.env.example`
   - `APP_NAME="Production Monitoring"`, `APP_URL=http://localhost:8080`, `APP_TIMEZONE=Asia/Jakarta`, `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=manufacturing_test`, `DB_USERNAME=app`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.
6. File: `config/app.php`
   - `timezone` membaca `env('APP_TIMEZONE', 'Asia/Jakarta')`.
7. Folder: `database/migrations/`
   - Kosong (migration bawaan dihapus).
8. File: `.gitattributes`
   - `*.sh text eol=lf`.

---

## 4. Scope of Changes

### A. Skeleton Laravel

1. `composer create-project laravel/laravel:^12.0` ke folder sementara dengan `--no-scripts --no-install`, pindahkan ke root project tanpa menimpa `README.md` dan `docs/`; gabungkan `.gitignore`.
2. Set `config.platform.php = 8.3`, buat `composer.lock` dengan `composer update --no-install`.
3. Buat `package-lock.json` (`npm install --package-lock-only`) agar `npm ci` bisa jalan.
4. Hapus migration bawaan dan `database/database.sqlite` bila ada.
5. Sesuaikan `.env.example` dan `config/app.php`.

### B. Docker

1. Tulis `docker-compose.yml`, `docker/php/Dockerfile`, `docker/php/entrypoint.sh`, `docker/nginx/default.conf`, `.gitattributes`.

### C. Docs

1. Perbarui `docs/architecture.md` (komponen Docker terverifikasi) dan `docs/ai-context.md` §4 (hapus catatan "belum dibuat").
2. Hapus item M1 dari `docs/backlog.md`.

---

## 5. Acceptance Test Matrix

| Case | Input | Expected Result | Status |
|---|---|---|---|
| Start bersih | `docker compose down -v && docker compose up -d` | Semua service jalan; `node` exit 0; `app`, `nginx`, `db` running | `[x]` |
| Halaman web | `curl -o /dev/null -w '%{http_code}' http://localhost:8080` | `200` | `[x]` |
| Data ter-import | `DB::table('work_order')->count()` via artisan di `app` | `1500` | `[x]` |
| Bootstrap otomatis | Cek `.env`, `vendor/`, `APP_KEY`, `public/build/manifest.json` setelah start bersih | Semua ada, `APP_KEY` tidak kosong | `[x]` |
| Boundary — restart tanpa reset | `docker compose restart` / `up -d` kedua | `.env`/`APP_KEY` tidak ditimpa, data tetap 1500 baris | `[x]` |
| Failure — tidak ada migrate | `SHOW TABLES` di `manufacturing_test` | Hanya 8 tabel dataset; tidak ada tabel `migrations`/`sessions`/`cache`/`jobs` | `[x]` |

---

## 6. Verification Commands

1. `docker compose down -v && docker compose up -d --build`
2. `docker compose ps -a`
3. `curl -s -o /dev/null -w '%{http_code}' http://localhost:8080`
4. `docker compose exec app php artisan tinker --execute="echo DB::table('work_order')->count();"`
5. `docker compose exec db mysql -uapp -psecret manufacturing_test -e 'SHOW TABLES'`

Expected:

1. HTTP `200`, count `1500`, 8 tabel saja.

Hasil: semua sesuai — HTTP `200`, `work_order` = `1500`, hanya 8 tabel dataset, `APP_KEY` terisi dan tidak berubah setelah restart, `public/build/manifest.json` ada dan aset ter-serve `200`, `config('app.timezone')` = `Asia/Jakarta`.

---

## 7. Out of Scope

1. Routing API dan controller (M3, M4).
2. Vue, `@vitejs/plugin-vue`, Chart.js, halaman dashboard (M5).
3. Query SQL Bagian A (M2).
4. README final (M6).

---

## 8. Completion Checklist

- [x] Section 1 fully filled and status set correctly.
- [x] All Non-Negotiable Technical Contract items implemented.
- [x] Acceptance Test Matrix completed.
- [x] Verification commands executed successfully.
- [x] No out-of-scope changes introduced.
