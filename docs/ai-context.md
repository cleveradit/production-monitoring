# AI Context & Development Mandates

All AI agents MUST read and follow these rules before proposing or implementing any changes.

> **Orientation (understand the project without reading code):** read in order — this document (mandates) → [architecture.md](architecture.md) (module map) → [data-model.md](data-model.md) (data structures) → [features/index.md](features/index.md) (per-feature details).

**Documentation language:** Indonesian (technical terms stay in English).

> **Sumber kebenaran requirement:** [initiate-file/soal-test.md](initiate-file/soal-test.md) (soal asli) → [initiate-file/prd.md](initiate-file/prd.md) (tafsiran + keputusan). Jika keduanya bertentangan, soal yang menang lalu PRD diperbaiki. Kode R1–R8, B1–B8, D1–D13, K1–K5 di bawah merujuk ke PRD.

---

## 1. Tech Stack (STRICT — no deviations)

| Komponen | Versi / pilihan |
|---|---|
| PHP | 8.3 (FPM) |
| Framework | Laravel 12 |
| Database | MySQL 8.4 LTS (deviasi dari MariaDB di Soal 7 — wajib dijelaskan di README) |
| Web server | Nginx `1.27-alpine` |
| Frontend | Vue 3 via Vite (`laravel-vite-plugin`, `@vitejs/plugin-vue`), di-mount di satu halaman Blade |
| CSS | Tailwind CSS v4 (`@tailwindcss/vite`), di-build — bukan CDN |
| Chart | Chart.js |
| Node (build aset) | `node:22-alpine` |
| Orkestrasi | Docker Compose |
| Timezone aplikasi | `Asia/Jakarta` |

Tidak ada login/auth, tidak ada automated test (PRD §8).

---

## 2. Architecture Mandates

1. **Database read-only kecuali satu endpoint.** Satu-satunya operasi tulis adalah `POST /api/production-results` (INSERT ke `production_result`). Tidak ada UPDATE/DELETE, tidak ada perubahan status WO (R3).
2. **Skema tidak disentuh.** Dilarang menambah/mengubah tabel, kolom, view, index, trigger, atau baris data awal (R1). Dilarang `php artisan migrate` dalam bentuk apa pun; migration bawaan Laravel dihapus (R2).
3. **Satu endpoint untuk dashboard.** Halaman `/` hanya memanggil `GET /api/dashboard`; data dibagi ke komponen lewat props (R7).
4. **Struktur kode:**
   - Query SQL Bagian A: `sql/soal-{n}-*.sql` (PRD §7.1–7.4).
   - Frontend: `resources/js/` dengan `api/client.js`, `composables/useDashboard.js`, `components/*.vue` (PRD §7.6).
   - Docker: `docker-compose.yml`, `docker/php/Dockerfile`, konfigurasi Nginx di `docker/`.
   - Dataset: `database/dataset.sql` (salinan asli, tidak diedit).
5. **Satu perintah menjalankan semuanya.** `docker compose up -d` dari kondisi bersih harus cukup: import DB, `composer install`, `APP_KEY`, dan build aset otomatis (R8).

---

## 3. Rules

**Rule 1 — CANCELLED dikecualikan dari metrik produksi:** target, good, reject, achievement, dan "Total WO" laporan produksi tidak menghitung WO `CANCELLED` (B1). Pengecualian: Total WO Soal 1 dan `total_order` endpoint mesin menghitung semua status (K1); `status_breakdown` menampilkan keempat status.

**Rule 2 — Rumus achievement agregat:** `SUM(good_qty) / SUM(target_qty) × 100`; target 0 → 0 (B2). Jangan merata-rata kolom `production_result.achievement` (D9).

**Rule 3 — Pembulatan:** SQL Bagian A memakai `ROUND()` ke bilangan bulat; API memakai `ROUND(…, 2)` dan dikirim sebagai number di JSON (B3).

**Rule 4 — Tanggal acuan:** semua pengelompokan tanggal memakai `DATE(production_result.actual_start)` (B4). "Hari ini" dashboard = `DATE(MAX(actual_start))`, bukan `CURDATE()`/`now()` (R6).

**Rule 5 — Agregasi per WO dulu:** agregasikan `production_result` dan `downtime` masing-masing per WO sebelum di-join ke `work_order`, agar target/qty tidak terhitung ganda (B6). Jangan join `downtime` bersamaan dengan `production_result`.

**Rule 6 — Satu WO maksimal satu hasil:** tidak ada constraint UNIQUE (D13); batasan dijaga validasi aplikasi di POST (B5).

**Rule 7 — Kewajiban SQL Bagian A:** Soal 2 wajib CTE tanpa subquery pengganti; Soal 3 agregasi di CTE sebelum window function (`RANK()` atas nilai yang dibulatkan, K3); Soal 4 deret tanggal wajib Recursive CTE dari MIN/MAX data, tanpa tanggal hardcode atau tabel kalender (R4, R5), dengan baris GRAND TOTAL ditampilkan (K5).

**Rule 8 — Kontrak API:** response selalu JSON; error validasi format standar Laravel (422); resource tidak ditemukan → 404 JSON. Nama field input memakai nama kolom DB (`good_qty`, `reject_qty`) (B8).

**Rule 9 — Konfigurasi Laravel tanpa tabel:** `.env` memakai `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` karena tabel bawaan Laravel tidak boleh dibuat (R2).

**Rule 10 — Verifikasi terhadap angka acuan:** setiap query/endpoint harus dicek terhadap angka verifikasi di PRD §7 (mis. Soal 2 baris teratas 38 / 87009 / 84416 / 679 / 97) sebelum dianggap selesai.

---

## 4. Local Development Environment

**Prasyarat:** Docker + Docker Compose. PHP/Composer/Node lokal tidak wajib (semua berjalan di container).

| Service | Peran | Port host |
|---|---|---|
| `app` | PHP 8.3-FPM + Laravel | — |
| `nginx` | Web server | `80` |
| `db` | MySQL 8.4, database `manufacturing_test` | `3307` |
| `node` | Sekali jalan: `npm ci && npm run build` | — |

| Tugas | Perintah |
|---|---|
| Start | `docker compose up -d` → buka `http://localhost` |
| Rebuild image | `docker compose up -d --build` |
| Build ulang aset frontend | `docker compose run --rm node` |
| Artisan | `docker compose exec app php artisan <cmd>` (kecuali `migrate`) |
| Clear cache | `docker compose exec app php artisan optimize:clear` |
| Import ulang database | `docker compose down -v && docker compose up -d` |

Catatan penting:

- `database/dataset.sql` di-mount ke `/docker-entrypoint-initdb.d/`; import hanya terjadi saat volume DB kosong. File ini menjalankan `DROP DATABASE` + `CREATE DATABASE`.
- Bind mount `./:/var/www/html` menutupi isi image; karena itu aset Vite dibangun service `node` ke folder project.
- `docker/php/entrypoint.sh` wajib ber-line ending LF.
- Port host `80` harus bebas; bila bentrok dengan service lain, ubah mapping `nginx.ports` (format `"host:container"`, misal `"8080:80"`) dan `APP_URL`.
- `node` menjalankan ulang `npm ci && npm run build` setiap `docker compose up -d`.
- File yang dibuat container (`vendor/`, `node_modules/`, `public/build/`) milik `root` di host, dan `storage/` + `bootstrap/cache/` milik `www-data` (uid 33). Pakai `sudo` atau `docker compose exec` bila perlu mengubahnya dari host.
- `composer.json` mengunci `config.platform.php = 8.3.0`; jalankan `composer` di container (`docker compose exec app composer …`) agar dependency tetap kompatibel PHP 8.3.

---

## 5. Documentation Workflow

**Rule 0 — All documentation/context MUST live in `docs/` (versioned in git), NOT in the AI's local memory.**
Developers switch between machines, and the AI's local memory is not committed, so it is unavailable on other machines. Therefore: decisions, ticket status, analysis results, and any context that must survive across sessions/machines must be written to the appropriate `docs/` file (planning, feature, decision-log, or backlog) — not stored as local memory. AI agents must not rely on local memory as the source of truth for this project.

All documentation is written in the **Documentation language** set at the top of this file.

Never write code without going through this flow first. Do not create `implementation_plan.md` as a standalone artifact — use the official planning files below.

**Step 1 — Backlog (`docs/backlog.md`)**
New bugs/ideas go here without ticket numbers. Status: `OPEN` (ready to be planned) or `BLOCKED`. Remove the item once it becomes a planning ticket.

**Step 2 — Planning (`docs/planning/TICKET-NUMBER-NAME.md`)**
Create the plan before writing any code. Use `docs/planning/_template-implementation-plan.md`. Register in `docs/planning/index.md`. Get explicit user approval before executing.

Status lifecycle: `DRAFT` → `REVIEW` → `READY` → `DONE`.
- `DRAFT`: decisions not locked, execution blocked.
- `REVIEW`: decisions locked, waiting for user manual approval — do NOT execute.
- `READY`: user gave explicit written approval in chat — execution allowed.
- `DONE`: implementation and verification complete.

Each plan must include: Business Decision Snapshot, Non-Negotiable Technical Contract (target files, method signatures, integration points, return shapes), Acceptance Test Matrix (min. 1 boundary + 1 failure case, or `N/A — <reason>` if not relevant), and Out of Scope section.

When a ticket is DONE: move its file to `docs/planning/Ticket-Implemented/`, remove its entry from `docs/planning/index.md` (do not delete the index file itself). Check both locations when numbering new tickets to avoid duplicates.

`docs/planning/current-session.md` is updated only when the user explicitly asks. Do not create or modify it on your own initiative.

**Step 3 — Feature Docs (`docs/features/`)**
Every shipped feature needs a doc here, registered in [`docs/features/index.md`](features/index.md). Use the lean template: `# Title` → `**Status:** Live` → `## Summary` (2–4 sentences) → relevant sections (Quick Reference / catalogs as tables) → `## Gotchas` (non-obvious things only) → `## Related` (links to related docs).

Style rules (mandatory): no decorative emoji in headers; no commit hash/branch/date in Status; no motivational/marketing prose; method/field/column catalogs are always tables; every claim is verified against the actual code (anything unverified is not written); cross-links use standard Markdown links (`[name](name.md)`). Structural docs (module map, data reference) live one level up: `docs/architecture.md` and `docs/data-model.md`.

**Step 4 — Decision Log (`docs/decision-log.md`)**
Record a decision only if it meets at least one criterion:
- A technical gotcha/trap that cannot be derived from reading the code
- A business trade-off with non-obvious consequences
- A correction of a previously wrong assumption or mandate

Do not record: code cleanup, minor UI changes, or anything already documented in `ai-context.md` itself. SUPERSEDED entries must be deleted, not kept. Use the entry format in [`docs/decision-log.md`](decision-log.md).
