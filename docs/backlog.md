# Backlog

This document lists all **unimplemented ideas, features, enhancements, and blocked items** for the project.

Before starting any item here, it **MUST** be converted into a detailed implementation plan in `docs/planning/` first!

---

## Format

Each backlog item follows this format:

```markdown
## [Feature/Idea Title]

**Status:** `OPEN` | `BLOCKED` *(blocker reason)*

**Summary:** 2-4 sentences describing what is wanted.

**Open questions (resolve during planning):**
1. [Question 1]
2. [Question 2]
3. [Question 3]
```

---

<!-- ADD BACKLOG ITEMS BELOW -->

## M3 — API dashboard

**Status:** `OPEN`

**Summary:** `GET /api/dashboard` dan `GET /api/dashboard/machine/{id}` sesuai PRD §7.5.1–7.5.2. `trend_7_days` harus 7 elemen termasuk 2026-12-27 bernilai 0.

## M4 — API production orders

**Status:** `OPEN`

**Summary:** `GET /api/production-orders` (search, filter, sort, pagination) dan `POST /api/production-results` dengan validasi PRD §7.5.3–7.5.4.

## M5 — Dashboard Vue

**Status:** `OPEN`

**Summary:** Halaman `/` dengan KPI, tabel top 10 mesin, dan 3 chart dari satu panggilan `GET /api/dashboard` (PRD §7.6). Responsif mobile–desktop.

## M6 — README & uji bersih

**Status:** `OPEN`

**Summary:** README final sesuai PRD §7.7 (cara jalan, `.env`, import DB, endpoint, deviasi MySQL, asumsi B1–B8, perilaku POST) lalu uji `docker compose down -v` → `up -d` tanpa langkah manual.
