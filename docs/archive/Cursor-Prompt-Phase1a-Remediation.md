# Cursor Prompt — CCRS Phase 1a Remediation

**Copy everything below this line into Cursor as the prompt.**

---

## Context

You are working on the CCRS (Contract & Merchant Agreement Repository System) project. The codebase has:
- **`apps/api`** — NestJS backend (TypeScript) using Supabase (PostgreSQL + Storage)
- **`apps/web`** — Next.js 16 frontend (React 19, TypeScript, shadcn/ui, NextAuth v5)
- **`supabase/migrations/`** — PostgreSQL schema

A comprehensive code audit has been completed against the CCRS Requirements v3 Board Edition 4. The full audit report is at `docs/Phase1a-Audit-and-Remediation.md`. Phase 1a covers Epics 1 (Foundation), 2 (Core Repository), 7 (Org Structure & Authority), 8 (Counterparty Management), 14 (Security & Compliance), and 17 (Counterparty Due Diligence).

## Instructions

Fix the following issues in priority order. Work through each section completely before moving to the next. After each section, verify the fix compiles (`npm run build` in both `apps/api` and `apps/web`).

---

## SECTION 1: Critical Bug Fixes (API)

### 1.1 Fix fuzzy duplicate detection
**File:** `apps/api/src/counterparties/counterparties.service.ts`
- Line 17: Remove unused `nameNorm` variable.
- Line 18: Change `.ilike('legal_name', legalName.trim())` to `.ilike('legal_name', '%' + legalName.trim() + '%')` so it performs a case-insensitive substring match instead of exact match.
- Consider also enabling the `pg_trgm` extension in the database and using a similarity threshold for better fuzzy matching in a future iteration.

### 1.2 Fix silent audit log failures
**File:** `apps/api/src/audit/audit.service.ts`
- In the `log()` method (lines 18-28): Capture the `{ error }` return from the Supabase `.insert()` call. If `error` is truthy, log it with `console.error` (or a proper logger) but do NOT throw — audit failures should not break mutations.
- This way audit issues are visible in logs but don't cascade to users.

### 1.3 Block contract creation for non-active counterparties
**File:** `apps/api/src/contracts/contracts.service.ts`
- In `createWithFile()`, before the storage upload, add a check:
  1. Query `counterparties` table for the given `counterpartyId`.
  2. If the counterparty's `status` is not `'Active'`, throw a `BadRequestException` with a clear message: `"Cannot create contract: counterparty is ${status}. Reason: ${status_reason}"`.
- Import `BadRequestException` from `@nestjs/common`.

### 1.4 Remove hardcoded JWT fallback secret
**File:** `apps/api/src/auth/jwt.strategy.ts`
- Line 16: Remove the `'ccrs-dev-secret-change-in-production'` fallback.
- Change to: `const secret = process.env.JWT_SECRET ?? process.env.AZURE_AD_CLIENT_SECRET ?? process.env.NEXTAUTH_SECRET;`
- Add immediately after: `if (!secret) throw new Error('FATAL: No JWT_SECRET, AZURE_AD_CLIENT_SECRET, or NEXTAUTH_SECRET configured. Cannot start.');`
- This prevents the app from starting with an insecure default.

### 1.5 Persist supportingDocumentRef on status changes
**File:** `apps/api/src/counterparties/counterparties.service.ts`
- In the `setStatus()` method, add `supporting_document_ref: dto.supportingDocumentRef ?? null` to the update payload.
- **File:** `supabase/migrations/` — Create a new migration file `20260217000001_add_supporting_doc_ref.sql`:
```sql
ALTER TABLE counterparties ADD COLUMN IF NOT EXISTS supporting_document_ref TEXT;
```

### 1.6 Replace generic `throw new Error()` with NestJS exceptions
**Files:** `apps/api/src/regions/regions.service.ts`, `entities/entities.service.ts`, `projects/projects.service.ts`, `counterparties/counterparties.service.ts`, `contracts/contracts.service.ts`, `audit/audit.service.ts`, `supabase/supabase.service.ts`
- Replace all `throw new Error(error.message)` with appropriate NestJS HTTP exceptions:
  - For Supabase errors containing "duplicate" or "unique" → `throw new ConflictException(error.message)`
  - For Supabase errors containing "not found" or when `data` is null on findOne → `throw new NotFoundException(error.message)`
  - For Supabase errors containing "foreign key" → `throw new BadRequestException(error.message)`
  - For all other Supabase errors → `throw new InternalServerErrorException(error.message)`
- Import these from `@nestjs/common`.

### 1.7 Wire SearchContractsDto to the contracts controller
**File:** `apps/api/src/contracts/contracts.controller.ts`
- Change the `search()` method to use `@Query() dto: SearchContractsDto` instead of individual `@Query()` decorators.
- Update the service call to use `dto.q`, `dto.regionId`, etc.
- Add upper-bound cap on limit: `const safeLimit = Math.min(dto.limit ?? 50, 500);`

---

## SECTION 2: Critical Bug Fixes (Frontend)

### 2.1 Remove Vercel boilerplate root page
**File:** `apps/web/src/app/page.tsx`
- Replace the entire contents with a redirect to the dashboard:
```tsx
import { redirect } from 'next/navigation';
export default function RootPage() {
  redirect('/');
}
```
Wait — since `(dashboard)/page.tsx` is already at `/` via the route group, the real fix is to **delete** `apps/web/src/app/page.tsx` entirely. The `(dashboard)/page.tsx` will then correctly handle the `/` route.

### 2.2 Fix multipart proxy forwarding
**File:** `apps/web/src/app/api/ccrs/[...path]/route.ts`
- In the POST handler, when forwarding multipart requests, do NOT set the `Content-Type` header manually. Let `fetch()` auto-generate the correct boundary:
```ts
const isMultipart = request.headers.get('content-type')?.includes('multipart');
const body = isMultipart ? await request.formData() : await request.text();
const headers: Record<string, string> = { Authorization: `Bearer ${token}` };
if (!isMultipart) {
  const ct = request.headers.get('content-type');
  if (ct) headers['Content-Type'] = ct;
}
const res = await fetch(`${API_BASE}/${path}`, { method: 'POST', body: body as BodyInit, headers });
```

### 2.3 Fix double-encoded error responses
**File:** `apps/web/src/app/api/ccrs/[...path]/route.ts`
- In all 4 HTTP method handlers, change the error path to try parsing the backend response as JSON first:
```ts
if (!res.ok) {
  let errorBody: string;
  try {
    const json = await res.json();
    errorBody = JSON.stringify(json);
  } catch {
    errorBody = JSON.stringify({ error: await res.text() });
  }
  return new NextResponse(errorBody, { status: res.status, headers: { 'Content-Type': 'application/json' } });
}
```

### 2.4 Create entity edit page
- Create `apps/web/src/app/(dashboard)/entities/[id]/page.tsx` with an `EditEntityForm` component (similar to `regions/[id]/page.tsx` and `edit-region-form.tsx`).
- The form should load the entity via `GET /api/ccrs/entities/${id}`, display the region (read-only or as a dropdown), name, and code fields, and submit via `PATCH /api/ccrs/entities/${id}`.

### 2.5 Create project edit page
- Create `apps/web/src/app/(dashboard)/projects/[id]/page.tsx` with an `EditProjectForm` component.
- Load via `GET /api/ccrs/projects/${id}`, display entity (read-only or dropdown), name, code, and submit via `PATCH /api/ccrs/projects/${id}`.

### 2.6 Add counterparty edit form
- Modify `apps/web/src/app/(dashboard)/counterparties/counterparty-detail-page.tsx` to include an edit mode.
- Add input fields for `legal_name`, `registration_number`, `address`, `jurisdiction`, `preferred_language`.
- Add a "Save" button that submits via `PATCH /api/ccrs/counterparties/${id}`.
- Change the "View / Edit" button text in `counterparties-list.tsx` to just "View" if you keep it view-only by default, or wire up a proper edit toggle.

### 2.7 Add counterparty status management UI
- On the counterparty detail page, add a status change section (visible to all users for now; role-restrict in a future pass):
  - A dropdown for new status (Active / Suspended / Blacklisted)
  - A required "Reason" text field
  - An optional "Supporting Document Reference" field
  - A "Change Status" button that calls `PATCH /api/ccrs/counterparties/${id}/status`

### 2.8 Fix error handling on all list fetches
- In every list component (`regions-list.tsx`, `entities-list.tsx`, `projects-list.tsx`, `counterparties-list.tsx`, `contracts-list.tsx`):
  - Add `.catch()` handler to display error state
  - Check `r.ok` before calling `r.json()`
  - Add an error state: `const [error, setError] = useState<string | null>(null);`
  - Display error message when set

### 2.9 Fix navigation highlighting for sub-routes
**File:** `apps/web/src/components/app-nav.tsx`
- Change `pathname === href` to `pathname === href || pathname.startsWith(href + '/')` for proper sub-route highlighting.

### 2.10 Replace `<a>` with `<Link>` on dashboard
**File:** `apps/web/src/app/(dashboard)/page.tsx`
- Import `Link` from `next/link` and replace all `<a href="...">` tags with `<Link href="...">`.

---

## SECTION 3: Missing API Modules

### 3.1 Signing Authority Module
Create a new NestJS module for signing authority management (Epic 7.2). The `signing_authority` table already exists in the database.

**Create these files:**
- `apps/api/src/signing-authority/signing-authority.module.ts`
- `apps/api/src/signing-authority/signing-authority.controller.ts`
- `apps/api/src/signing-authority/signing-authority.service.ts`
- `apps/api/src/signing-authority/dto/create-signing-authority.dto.ts`
- `apps/api/src/signing-authority/dto/update-signing-authority.dto.ts`

**Endpoints:**
- `POST /signing-authority` — Create a signing authority rule
- `GET /signing-authority?entityId=&projectId=` — List rules (filtered by entity/project)
- `GET /signing-authority/:id` — Get single rule
- `PATCH /signing-authority/:id` — Update rule
- `DELETE /signing-authority/:id` — Delete rule

**DTO fields (from schema):** `entityId` (required UUID), `projectId` (optional UUID), `userId` (required string), `userEmail` (optional string), `roleOrName` (required string), `contractTypePattern` (optional string).

**Apply `@Roles('System Admin')` to all endpoints.**

Register the module in `app.module.ts`.

### 3.2 Counterparty Contacts Module
Create a new NestJS module for counterparty contacts/signatories (Epic 8.1). The `counterparty_contacts` table already exists.

**Create these files:**
- `apps/api/src/counterparty-contacts/counterparty-contacts.module.ts`
- `apps/api/src/counterparty-contacts/counterparty-contacts.controller.ts`
- `apps/api/src/counterparty-contacts/counterparty-contacts.service.ts`
- `apps/api/src/counterparty-contacts/dto/create-contact.dto.ts`
- `apps/api/src/counterparty-contacts/dto/update-contact.dto.ts`

**Endpoints:**
- `POST /counterparties/:counterpartyId/contacts` — Add a contact
- `GET /counterparties/:counterpartyId/contacts` — List contacts for a counterparty
- `PATCH /counterparty-contacts/:id` — Update a contact
- `DELETE /counterparty-contacts/:id` — Delete a contact

**DTO fields (from schema):** `name` (required string), `email` (optional string), `role` (optional string), `isSigner` (optional boolean).

Audit all mutations. Register the module in `app.module.ts`.

---

## SECTION 4: API Infrastructure Improvements

### 4.1 Add global exception filter
Create `apps/api/src/common/http-exception.filter.ts`:
- Catch all exceptions globally
- Map known Supabase error patterns to appropriate HTTP codes
- Log the original error details
- Return structured JSON responses

Register in `main.ts` via `app.useGlobalFilters(...)`.

### 4.2 Add Helmet security headers
- Run `npm install helmet` in `apps/api`
- Add `app.use(helmet())` in `main.ts`

### 4.3 Add structured request logging
- Add a request logging middleware or interceptor that logs: method, path, status code, response time, user ID
- Use NestJS Logger or a structured logging library

### 4.4 Add pagination support
- Create a shared `PaginationDto` with `limit` (max 500, default 50) and `offset` (default 0) fields
- Apply to all list endpoints (regions, entities, projects, counterparties, contracts, audit)
- Return `{ data: T[], total: number, limit: number, offset: number }` wrapper

### 4.5 Add RBAC to all controllers
Apply appropriate `@Roles()` decorators:
- Regions/Entities/Projects controllers: Write operations → `@Roles('System Admin')`; Read operations → allow all authenticated
- Counterparties: Write → `@Roles('System Admin', 'Legal', 'Commercial')`; Status changes → `@Roles('Legal')`
- Contracts: Upload → `@Roles('System Admin', 'Legal', 'Commercial')`; Read → all authenticated
- Audit: Already done (System Admin, Legal, Audit)
- Signing Authority: `@Roles('System Admin')`

Register `RolesGuard` as a global guard alongside `JwtAuthGuard` in `app.module.ts`.

### 4.6 Fix CORS whitespace trimming
**File:** `apps/api/src/main.ts:15`
Change: `origin: process.env.CORS_ORIGIN?.split(',') ?? ['http://localhost:3000']`
To: `origin: process.env.CORS_ORIGIN?.split(',').map(s => s.trim()) ?? ['http://localhost:3000']`

### 4.7 Remove redundant @UseGuards(JwtAuthGuard)
Since `JwtAuthGuard` is already a global guard via `APP_GUARD`, remove all per-controller and per-route `@UseGuards(JwtAuthGuard)` decorators from:
- `regions.controller.ts` (5 per-route occurrences)
- `entities.controller.ts` (class-level)
- `projects.controller.ts` (class-level)
- `counterparties.controller.ts` (class-level)
- `contracts.controller.ts` (class-level)
- `audit.controller.ts` (keep `RolesGuard` only, remove `JwtAuthGuard`)

### 4.8 Fix health check
**File:** `apps/api/src/health/health.controller.ts`
Change the DB probe from querying the `regions` table to a neutral query:
```ts
const { error } = await this.supabase.getClient().rpc('', {}).throwOnError();
```
Or use raw SQL: query any system table or use `SELECT 1` via Supabase's `.rpc()`.

### 4.9 Capture IP address in audit entries
- In each controller, extract the request IP from the NestJS request object (`@Req() req`)
- Pass `ipAddress: req.ip || req.headers['x-forwarded-for']` to the audit service
- Alternatively, create an interceptor that automatically captures IP for all audited operations.

### 4.10 Add updated_at database trigger
Create a new migration:
```sql
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER set_updated_at BEFORE UPDATE ON regions FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON entities FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON projects FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON counterparties FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON contracts FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON counterparty_contacts FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON signing_authority FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

---

## SECTION 5: Frontend Infrastructure Improvements

### 5.1 Create shared types file
Create `apps/web/src/lib/types.ts` with interfaces matching the API response shapes:
```ts
export interface Region { id: string; name: string; code: string | null; created_at: string; updated_at: string; }
export interface Entity { id: string; region_id: string; name: string; code: string | null; created_at: string; updated_at: string; regions?: { name: string }; }
export interface Project { id: string; entity_id: string; name: string; code: string | null; created_at: string; updated_at: string; entities?: { name: string; code: string | null; region_id: string }; }
export interface Counterparty { id: string; legal_name: string; registration_number: string | null; address: string | null; jurisdiction: string | null; status: 'Active' | 'Suspended' | 'Blacklisted'; status_reason: string | null; preferred_language: string; created_at: string; updated_at: string; counterparty_contacts?: CounterpartyContact[]; }
export interface CounterpartyContact { id: string; counterparty_id: string; name: string; email: string | null; role: string | null; is_signer: boolean; }
export interface Contract { id: string; region_id: string; entity_id: string; project_id: string; counterparty_id: string; contract_type: 'Commercial' | 'Merchant'; title: string | null; workflow_state: string; signing_status: string | null; storage_path: string | null; file_name: string | null; created_at: string; updated_at: string; regions?: Region; entities?: Entity; projects?: Project; counterparties?: Counterparty; }
```
Update all components to import from this file instead of defining inline types.

### 5.2 Add contract search and filter UI
**File:** `apps/web/src/app/(dashboard)/contracts/contracts-list.tsx`
- Add a search input for full-text search (`q` parameter)
- Add dropdown filters for region, entity, project, contract type, and workflow state
- Wire the filters to the API call as query parameters
- Add pagination controls (Next/Previous with offset)

### 5.3 Add audit trail UI
**File:** `apps/web/src/app/(dashboard)/audit/page.tsx`
- Replace the placeholder text with a functional audit viewer
- Add date range inputs (from/to)
- Add optional filters for resource type and actor
- Add an "Export" button that calls `GET /api/ccrs/audit/export?from=...&to=...` and downloads the result
- Display results in a table (use the existing `table.tsx` shadcn component)

### 5.4 Refactor API proxy to reduce duplication
**File:** `apps/web/src/app/api/ccrs/[...path]/route.ts`
- Extract a shared `proxyRequest(request, method)` function that handles: auth check, token extraction, forwarding, error handling
- Call it from GET, POST, PATCH, DELETE handlers

### 5.5 Delete dead code
- Delete `apps/web/src/lib/api.ts` (never imported anywhere)
- Delete `apps/web/src/app/page.tsx` (shadowed by dashboard route group)
- Consider removing unused shadcn components (`table.tsx`, `dialog.tsx`, `dropdown-menu.tsx`, `tabs.tsx`) or keep them if they'll be used in upcoming sections

### 5.6 Filter out non-active counterparties in contract upload form
**File:** `apps/web/src/app/(dashboard)/contracts/upload-contract-form.tsx`
- Change the counterparty fetch to: `fetch('/api/ccrs/counterparties?status=Active')`
- This prevents users from even selecting a Suspended or Blacklisted counterparty

### 5.7 Add authenticated user redirect from /login
**File:** `apps/web/src/middleware.ts`
- Add: if user IS logged in AND is on `/login`, redirect to `/`

---

## SECTION 6: Testing Foundation

### 6.1 API unit tests
Create at minimum one test file per service:
- `apps/api/src/regions/regions.service.spec.ts`
- `apps/api/src/counterparties/counterparties.service.spec.ts`
- `apps/api/src/contracts/contracts.service.spec.ts`
- `apps/api/src/audit/audit.service.spec.ts`
- `apps/api/src/auth/jwt.strategy.spec.ts`

Use `@nestjs/testing` with mocked `SupabaseService`. Test at minimum:
- Successful CRUD operations
- Error handling (Supabase errors map to correct HTTP exceptions)
- Counterparty status check blocks contract creation
- Fuzzy duplicate detection logic
- Audit logging is called on mutations

### 6.2 Fix CI pipeline
**File:** `.github/workflows/ci.yml`
- Remove `continue-on-error: true` from the install, lint, and test steps (keep it only on the root job which is intentionally a no-op)
- Ensure both frontend build and backend build/test actually fail the pipeline on errors

---

## SECTION 7: Database Migrations

Create new migration file `supabase/migrations/20260217000001_phase1a_fixes.sql`:

```sql
-- Enable pg_trgm for fuzzy matching (Epic 8.2)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Add GIN trigram index on counterparty legal_name for fuzzy search
CREATE INDEX IF NOT EXISTS idx_counterparties_legal_name_trgm ON counterparties USING GIN (legal_name gin_trgm_ops);

-- Add supporting_document_ref to counterparties (Epic 17.1)
ALTER TABLE counterparties ADD COLUMN IF NOT EXISTS supporting_document_ref TEXT;

-- Add updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply updated_at triggers to all tables
DO $$
DECLARE
  t TEXT;
BEGIN
  FOR t IN SELECT unnest(ARRAY['regions','entities','projects','counterparties','contracts','counterparty_contacts','signing_authority'])
  LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS set_updated_at ON %I', t);
    EXECUTE format('CREATE TRIGGER set_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()', t);
  END LOOP;
END;
$$;
```

---

## Completion Checklist

After all changes:
1. [ ] `cd apps/api && npm run build` — no errors
2. [ ] `cd apps/web && npm run build` — no errors
3. [ ] `cd apps/api && npm run test` — tests pass
4. [ ] `cd apps/api && npm run lint` — no errors
5. [ ] `cd apps/web && npm run lint` — no errors
6. [ ] New migration applied to Supabase
7. [ ] All 7 sections addressed
