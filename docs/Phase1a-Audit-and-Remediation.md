# CCRS Phase 1a — Code Audit & Remediation Plan

**Date:** 2026-02-17
**Source:** Cross-reference of CCRS Requirements v3 Board Edition 4, CCRS-Backlog-and-Build-Plan.md, and all generated code
**Auditor:** Claude Opus 4.6

---

## Executive Summary

Phase 1a code (apps/api, apps/web, supabase migration) covers the **skeleton** of Epics 1, 2, 7, 8, 14, and 17 but has **30 bugs**, **18+ missing features**, and **28 architectural concerns** that must be resolved before the system is functional. The most critical issues are:

1. **Broken fuzzy duplicate detection** — counterparty matching is exact-only, defeating Epic 8.2
2. **No counterparty status enforcement** — Blacklisted counterparties can still have contracts created (Epic 17.1)
3. **Silent audit failures** — audit log inserts never check for errors (Epic 14)
4. **Zero API coverage** for `signing_authority` and `counterparty_contacts` tables (Epics 7.2, 8.1)
5. **Frontend broken pages** — entity/project edit links are 404s; root page shows Vercel boilerplate
6. **Multipart proxy corruption** — contract file uploads through the Next.js proxy may fail
7. **Zero test coverage** — no unit or integration tests exist anywhere
8. **Hardcoded JWT fallback secret** in committed source code

### Technology Stack Note

The Requirements v3 Board Edition 4 specifies **Python (FastAPI)** as the backend. The build plan consciously retains **NestJS (Node.js)** with the note: *"A future migration to FastAPI can be scheduled separately if the board adopts the full stack change."* This audit addresses the current NestJS implementation.

---

## Issue Registry

### CRITICAL BUGS (must fix before any testing)

| # | Location | Issue | Impact |
|---|----------|-------|--------|
| B1 | `api/src/counterparties/counterparties.service.ts:18` | `ilike` without `%` wildcards = exact match only, not fuzzy | Duplicate detection misses nearly all real duplicates |
| B2 | `api/src/audit/audit.service.ts:18-28` | Supabase `.insert()` return error is never checked | Audit trail silently corrupted; violates Epic 14 |
| B3 | `api/src/contracts/contracts.service.ts` (entire `createWithFile`) | No check of counterparty status before contract creation | Blacklisted counterparties can have new contracts (violates Epic 17.1) |
| B4 | `api/src/auth/jwt.strategy.ts:16` | Hardcoded fallback `'ccrs-dev-secret-change-in-production'` | Any attacker can forge JWTs if env vars unset; violates "no secrets in code" |
| B5 | `web/src/app/page.tsx` vs `web/src/app/(dashboard)/page.tsx` | Both compete for `/` route; Vercel boilerplate wins | Users land on unbranded boilerplate page |
| B6 | `web/src/app/api/ccrs/[...path]/route.ts:42-49` | Multipart FormData re-serialization with original boundary header | Contract file uploads through proxy may fail |
| B7 | `api/src/counterparties/dto/counterparty-status.dto.ts:10-12` | `supportingDocumentRef` accepted but never persisted to DB | Supporting docs for status changes silently lost |

### HIGH-PRIORITY BUGS

| # | Location | Issue |
|---|----------|-------|
| B8 | `api/src/contracts/dto/search-contracts.dto.ts` | Fully defined DTO never used; search params bypass all validation |
| B9 | `web/src/app/(dashboard)/entities/` | No `[id]/page.tsx` — Edit link is 404 |
| B10 | `web/src/app/(dashboard)/projects/` | No `[id]/page.tsx` — Edit link is 404 |
| B11 | `web/src/app/api/ccrs/[...path]/route.ts:6-10` | Session cookie sent as Bearer token; fails if AUTH_SECRET ≠ JWT_SECRET |
| B12 | `web/src/app/api/ccrs/[...path]/route.ts:27,52,77,97` | Backend JSON errors double-encoded in proxy response |
| B13 | All 24 `throw new Error()` across api services | Generic errors → HTTP 500 instead of proper 400/404/409 |
| B14 | `api/src/contracts/contracts.controller.ts:40` | `limit` has no upper bound or NaN protection |
| B15 | `api/src/audit/audit.controller.ts:19,32` | Same `limit` issue on audit endpoints |
| B16 | `api/src/main.ts:15` | `CORS_ORIGIN.split(',')` doesn't trim whitespace |
| B17 | `api/src/health/health.controller.ts:13` | Health check queries `regions` table instead of neutral `SELECT 1` |

### MEDIUM-PRIORITY BUGS

| # | Location | Issue |
|---|----------|-------|
| B18 | `web/` counterparty detail page | "View / Edit" label but no edit functionality |
| B19 | `web/` contract-detail-page.tsx:9 | `useState<unknown>` with unsafe `as` casts |
| B20 | `web/` counterparty-detail-page.tsx:21-29 | Silent redirect on any fetch failure |
| B21 | `web/` upload-contract-form.tsx:98 | `Option[]` type mismatch with API response shape |
| B22 | `web/` edit-region-form.tsx:18-26 | No error handling on initial data fetch |
| B23 | `web/` all list components | No `.catch()` on fetch chains; errors swallowed |
| B24 | `api/` all service audit calls | Audit failure after successful mutation → misleading 500 |
| B25 | `web/` auth.ts:2 | `azure-ad` provider import deprecated in NextAuth v5 (should be `microsoft-entra-id`) |

### LOW-PRIORITY / CODE QUALITY

| # | Location | Issue |
|---|----------|-------|
| B26 | `api/src/counterparties/counterparties.service.ts:17` | Unused variable `nameNorm` |
| B27 | `api/src/contracts/contracts.service.ts:22` | Unused variable `ext` |
| B28 | `web/` app-nav.tsx:28 | Nav highlighting uses strict equality; sub-routes not highlighted |
| B29 | `web/` dashboard page.tsx | Uses `<a>` instead of `<Link>` (full-page reloads) |
| B30 | `api/` all controllers | Redundant `@UseGuards(JwtAuthGuard)` (already global) |

---

## Missing Phase 1a Features

### Epic 1 — Foundation & Infrastructure
- [ ] **RBAC enforcement** on all controllers (currently only audit has `@Roles()`)
- [ ] **Failed auth attempt logging** in audit trail
- [ ] **Remove hardcoded JWT fallback** — app should throw on missing secret in production
- [ ] **Config change auditing** mechanism

### Epic 2 — Core Repository
- [ ] **Contract update** endpoint (`PATCH /contracts/:id`)
- [ ] **Contract delete** endpoint (`DELETE /contracts/:id`)
- [ ] **Workflow state transitions** (`draft → review → approved → signed → archived`)
- [ ] **File versioning / re-upload** for existing contracts
- [ ] **Immutability enforcement** on executed contracts
- [ ] **Pagination** on contract search (offset/cursor)
- [ ] **Wire SearchContractsDto** to the controller for proper validation
- [ ] **Contract search UI** — search bar, filters, pagination in frontend
- [ ] **Download audit logging** — track who downloads contracts

### Epic 7 — Org Structure & Authority Matrix
- [ ] **Signing authority CRUD** — module, controller, service, DTOs (table exists, zero API code)
- [ ] **Activation concept** on regions/entities/projects (default workflow + signers required before use)
- [ ] **Entity edit page** (frontend)
- [ ] **Project edit page** (frontend)
- [ ] **Delete UI** for regions/entities/projects

### Epic 8 — Counterparty Management
- [ ] **Counterparty contacts CRUD** — module, controller, service, DTOs (table exists, zero API code)
- [ ] **Fix fuzzy matching** — use `%` wildcards or `pg_trgm` similarity
- [ ] **Merge/link duplicates** — endpoint and UI
- [ ] **Counterparty edit form** (frontend)
- [ ] **Counterparty completeness check** before signature operations

### Epic 14 — Security & Compliance
- [ ] **Fix silent audit failures** — check error on every insert
- [ ] **Wrap audit calls in try/catch** — don't fail mutations if audit fails
- [ ] **Capture IP address** in audit entries
- [ ] **Audit trail UI** — viewer, date range picker, export button
- [ ] **Audit download/access events** for contracts

### Epic 17 — Counterparty Due Diligence
- [ ] **Block contract creation** for non-active counterparties (API enforcement)
- [ ] **Persist supportingDocumentRef** on status changes
- [ ] **Counterparty status management UI** — change status from frontend
- [ ] **Status change notifications** — notify users with active contracts
- [ ] **Override request flow** — authorized role override with audit

---

## Architectural Gaps

| # | Area | Gap |
|---|------|-----|
| A1 | API | No global exception filter (all errors → 500) |
| A2 | API | No API versioning / global prefix |
| A3 | API | No pagination on any list endpoint |
| A4 | API | No Swagger/OpenAPI documentation |
| A5 | API | No rate limiting / throttling |
| A6 | API | No security headers (Helmet) |
| A7 | API | No structured request logging |
| A8 | API | `updated_at` set manually (should be DB trigger) |
| A9 | API | Service_role key bypasses RLS; no app-level scoping |
| A10 | API | Inconsistent error handling patterns across modules |
| A11 | API | No audit date parameter validation |
| A12 | API+Web | Zero unit or integration tests |
| A13 | Web | No form validation library (Zod/react-hook-form) |
| A14 | Web | No shared TypeScript types for API responses |
| A15 | Web | No data fetching library (SWR/React Query) |
| A16 | Web | No error boundaries |
| A17 | Web | No loading skeletons |
| A18 | Web | Card grids don't scale to 50k+ contracts (need tables) |
| A19 | Web | Dead code: `lib/api.ts` never used |
| A20 | Web | 4 unused shadcn components (table, dialog, dropdown-menu, tabs) |
| A21 | Web | Raw `<select>` instead of styled Select component |
| A22 | Web | Proxy route duplicates logic across 4 HTTP methods |
| A23 | Web | No redirect for authenticated users on `/login` |
| A24 | Web | Credentials provider ignores password; all users share `id: 'dev-1'` |
| A25 | CI | `continue-on-error: true` on most CI steps masks real failures |
| A26 | CI | Root-level `npm run test/lint` are no-ops |
| A27 | Schema | No `updated_at` trigger (relies on application code) |
| A28 | Schema | No `pg_trgm` extension enabled for fuzzy matching |
