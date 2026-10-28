# Decision Log

Recording criteria: see [ai-context.md §5 Step 4](ai-context.md#5-documentation-workflow).

Entry format (4 fields):

```markdown
## DEC-XXX — Short title

**Decision:** What was decided.

**Why:** Why — the constraint, bug, or wrong assumption.

**Impact:** Concrete consequences that are not obvious from the code.

**Tickets:** TICKET-XXX (optional)
```

---

<!-- ADD DECISION LOG ENTRIES BELOW -->

## DEC-001 — Skeleton Laravel dibuat dengan `--no-scripts`

**Decision:** Skeleton dibuat dengan `composer create-project laravel/laravel:^12.0 --no-scripts --no-install`; `composer.lock` di-generate dengan `config.platform.php = 8.3.0`.

**Why:** Script `post-create-project-cmd` bawaan Laravel 12 menjalankan `php artisan migrate`, yang melanggar R2. PHP lokal developer (8.5) lebih baru dari container (8.3), sehingga lock tanpa pin platform bisa memilih paket yang tidak jalan di container.

**Impact:** Jangan membuat ulang skeleton atau menjalankan `composer update` tanpa pin platform. Tidak ada `database/database.sqlite` maupun migration bawaan; jangan ditambahkan kembali.

**Tickets:** TICKET-001
