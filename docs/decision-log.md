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

## DEC-002 — MySQL 8.4 dipertahankan karena MariaDB tidak punya `GROUPING()`

**Decision:** Service `db` tetap MySQL 8.4 walaupun Soal 7 menyebut MariaDB; deviasi dijelaskan di README.

**Why:** Query Soal 4 memakai `GROUPING()` untuk melabeli baris DATE / MONTH / YEAR / GRAND TOTAL hasil `WITH ROLLUP`. Diuji di MariaDB 11.4.13: Recursive CTE, `RANK()`, dan `WITH ROLLUP` jalan, tetapi `GROUPING()` gagal dengan `ERROR 1305 (42000): FUNCTION GROUPING does not exist`.

**Impact:** Mengganti image `db` ke MariaDB membuat `sql/soal-4-recursive-trend.sql` gagal; label level harus diturunkan dari `NULL` kolom (rawan ambigu). Query dan endpoint lain tidak bergantung pada fitur khusus MySQL.
