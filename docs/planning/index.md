# Implementation Plan Index

Use this file to orchestrate active ticket plans with clear order and dependency.

Status meanings (`DRAFT` / `REVIEW` / `READY` / `DONE`): see [ai-context.md §5 Step 2](../ai-context.md#5-documentation-workflow).

## Execution Order

| # | Ticket | Status | Depends On |
|---|---|---|---|
| 1 | [TICKET-002 — SQL Bagian A (Soal 1–4)](TICKET-002-sql-bagian-a.md) | `REVIEW` | TICKET-001 |

## Global Agent Rules

1. Only execute a ticket the user explicitly asks for, and only if its status is `READY`.
2. Read each ticket's `Business Decision Snapshot` before any code change.
3. Follow each ticket's `Non-Negotiable Technical Contract` exactly.
4. Stop and report if a dependency ticket is incomplete or inconsistent.
