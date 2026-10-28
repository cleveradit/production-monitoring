# Feature Docs — Index

Table of contents for all feature docs. Template and writing conventions: see [ai-context.md §5 Step 3](../ai-context.md#5-documentation-workflow).

For a full understanding, start with [architecture.md](../architecture.md) and [data-model.md](../data-model.md) first.

<!-- ADD ONE ROW PER FEATURE to the table below. Format:
| [file-name](file-name.md) | Short description (1 sentence) | `path/to/file` |
-->

| Doc | Description | Key files |
|---|---|---|
| [sql-bagian-a](sql-bagian-a.md) | Query SQL Soal 1–4: many-to-many, CTE, window function, Recursive CTE + ROLLUP | `sql/` |
| [api-dashboard](api-dashboard.md) | `GET /api/dashboard` dan `GET /api/dashboard/machine/{id}` | `app/Services/DashboardService.php` |
| [api-production-orders](api-production-orders.md) | `GET /api/production-orders` (search/filter/sort/pagination) dan `POST /api/production-results` | `app/Services/ProductionOrderService.php`, `app/Services/ProductionResultService.php` |
| [dashboard-web](dashboard-web.md) | Halaman `/` Vue 3 + Chart.js: 6 KPI, 3 chart, tabel Top 10 mesin dari satu `GET /api/dashboard` | `resources/js/components/DashboardPage.vue` |
