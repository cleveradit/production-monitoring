# Implementation Plan: [TICKET-ID] ([Short Title])

**Ticket:** `[TICKET-ID]`  
**Status:** `DRAFT` | `REVIEW` | `READY` | `DONE`  
**Target Audience:** AI Developer Agents  
**Depends On:** `[Ticket ID or None]`

---

## 1. Business Decision Snapshot (Approved)

Fill all required decisions before implementation starts.

| Item | Approved Value |
|---|---|
| [Decision 1] | `[FILL]` |
| [Decision 2] | `[FILL]` |
| [Decision 3] | `[FILL or NULL]` |

Rules:

1. If any required `[FILL]` is missing, status MUST stay `DRAFT`.
2. Agent MUST NOT write production code while status is `DRAFT` or `REVIEW`.
3. Set status to `REVIEW` when the plan is ready for user review.
4. Set status to `READY` only after the user explicitly approves the plan in the chat.

---

## 2. Objective

Describe the expected business outcome in 2-4 sentences.

---

## 3. Non-Negotiable Technical Contract

List fixed technical targets. Do not use ambiguous alternatives.

1. File: `[absolute or repo path]`
   - Change: `[exact requirement]`
2. File: `[absolute or repo path]`
   - Method signature: ``[exact signature]``
   - Return shape: `[exact array/object contract]`
3. File: `[absolute or repo path]`
   - Integration point: `[exact location/flow]`

Rules:

1. No "or relevant file/service" wording.
2. No scope expansion without explicit approval.

---

## 4. Scope of Changes

Add/remove areas as needed.

### A. [Area A]

1. [Step]
2. [Step]

### B. [Area B]

1. [Step]
2. [Step]

### C. [Area C]

1. [Step]
2. [Step]

---

## 5. Acceptance Test Matrix

Define concrete input-output scenarios.

| Case | Input | Expected Result | Status |
|---|---|---|---|
| [Case 1] | [Input] | [Pass/Fail behavior] | `[ ]` |
| [Case 2] | [Input] | [Pass/Fail behavior] | `[ ]` |
| [Boundary Case] | [Input] | [Boundary behavior] | `[ ]` |

Notes:

1. Include at least one boundary case.
2. Include at least one negative/failure case.
3. If a boundary or failure case is not relevant (e.g. docs/config-only ticket), write `N/A — <reason>` instead.

---

## 6. Verification Commands

Run and record results (add/remove commands as needed):

1. `[command 1]`
2. `[command 2]`
3. `[command 3]`

Expected:

1. [Expected outcome 1]
2. [Expected outcome 2]

---

## 7. Out of Scope

1. [Explicitly excluded work]
2. [Explicitly excluded work]
3. [Explicitly excluded work]

---

## 8. Completion Checklist

- [ ] Section 1 fully filled and status set correctly.
- [ ] All Non-Negotiable Technical Contract items implemented.
- [ ] Acceptance Test Matrix completed.
- [ ] Verification commands executed successfully.
- [ ] No out-of-scope changes introduced.

