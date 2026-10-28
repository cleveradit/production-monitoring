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

## M6 — README & uji bersih

**Status:** `OPEN`

**Summary:** README final sesuai PRD §7.7 (cara jalan, `.env`, import DB, endpoint, deviasi MySQL, asumsi B1–B8, perilaku POST) lalu uji `docker compose down -v` → `up -d` tanpa langkah manual.

**Open questions (resolve during planning):**
1. `.env.example` saat ini `APP_ENV=local`, `APP_DEBUG=true`, sehingga error JSON API menampilkan stack trace. Ubah ke `production`/`false` untuk penyerahan?
