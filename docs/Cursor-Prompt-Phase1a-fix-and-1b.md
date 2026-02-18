# Cursor Prompt — CCRS Phase 1a-fix + Phase 1b Implementation

**Copy everything below this line into Cursor as the prompt.**

---

## Context

You are working on the CCRS (Contract & Merchant Agreement Repository System) project:
- **`apps/api`** — Python FastAPI backend (Phase 1a complete — 41 endpoints, Supabase, structured logging, RBAC)
- **`apps/web`** — Next.js 16 frontend (React 19, TypeScript, shadcn/ui, NextAuth v5) — **has 11 unresolved bugs from Phase 1a**
- **`supabase/migrations/`** — PostgreSQL schema (2 migrations applied)

Reference documents:
- Full build plan: `docs/CCRS-Phased-Build-Plan-Remaining.md`
- Requirements: `CCRS Requirements v3 Board Edition 4.docx` (key sections extracted in the build plan)
- Phase 1a audit: `docs/Phase1a-Audit-and-Remediation.md`
- Database schema: `supabase/migrations/20260216000000_phase1a_schema.sql` and `20260217000001_phase1a_fastapi_fixes.sql`

The Next.js frontend proxies API calls through `apps/web/src/app/api/ccrs/[...path]/route.ts` to the FastAPI backend at `NEXT_PUBLIC_API_URL` (default `http://localhost:4000`).

## Instructions

Implement all sections below in order. After each section, verify compilation:
- Backend: `cd apps/api && python -m py_compile app/main.py` (or `pytest tests/ -v`)
- Frontend: `cd apps/web && npm run build`

Run tests after each backend section. Run `npm run build` after each frontend section.

---

# PART A: PHASE 1a-fix — FRONTEND REMEDIATION

## A1. Critical Route and Proxy Fixes

### A1.1 Delete Vercel boilerplate root page
**Delete** `apps/web/src/app/page.tsx` entirely. The `(dashboard)` route group's `page.tsx` already handles `/` — removing this file lets it work correctly.

### A1.2 Fix multipart proxy boundary corruption
**File:** `apps/web/src/app/api/ccrs/[...path]/route.ts`

In the `POST` handler, when the request is multipart, do NOT forward the original `Content-Type` header. Let `fetch()` auto-generate the correct boundary for the re-serialized FormData:

```typescript
export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  const path = (await params).path.join('/');

  const isMultipart = request.headers.get('content-type')?.includes('multipart');
  const body = isMultipart ? await request.formData() : await request.text();

  // For multipart: let fetch auto-set Content-Type with correct boundary
  // For JSON/other: forward the original Content-Type
  const headers: Record<string, string> = { Authorization: `Bearer ${token}` };
  if (!isMultipart) {
    const ct = request.headers.get('content-type');
    if (ct) headers['Content-Type'] = ct;
  }

  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'POST',
    body: body as BodyInit,
    headers,
  });
  return forwardResponse(res);
}
```

### A1.3 Fix double-encoded error responses + deduplicate proxy logic

Refactor the entire proxy file to use a shared `forwardResponse` helper and a shared `proxyRequest` pattern. Replace ALL four HTTP method handlers:

```typescript
import { NextRequest, NextResponse } from 'next/server';
import { auth } from '@/auth';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4000';

function getBearerToken(request: NextRequest, session: { accessToken?: string } | null): string | null {
  if (session?.accessToken) return session.accessToken;
  const cookie = request.cookies.get('authjs.session-token') ?? request.cookies.get('next-auth.session-token');
  return cookie?.value ?? null;
}

async function forwardResponse(res: Response): Promise<NextResponse> {
  const contentType = res.headers.get('Content-Type') ?? 'application/json';
  const body = await res.arrayBuffer();
  return new NextResponse(body, {
    status: res.status,
    headers: { 'Content-Type': contentType },
  });
}

async function authenticate(request: NextRequest): Promise<{ token: string } | NextResponse> {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  return { token };
}

export async function GET(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const qs = new URL(request.url).searchParams.toString();
  const res = await fetch(`${API_BASE}/${path}${qs ? `?${qs}` : ''}`, {
    headers: { Authorization: `Bearer ${authResult.token}` },
  });
  return forwardResponse(res);
}

export async function POST(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const isMultipart = request.headers.get('content-type')?.includes('multipart');
  const body = isMultipart ? await request.formData() : await request.text();
  const headers: Record<string, string> = { Authorization: `Bearer ${authResult.token}` };
  if (!isMultipart) {
    const ct = request.headers.get('content-type');
    if (ct) headers['Content-Type'] = ct;
  }
  const res = await fetch(`${API_BASE}/${path}`, { method: 'POST', body: body as BodyInit, headers });
  return forwardResponse(res);
}

export async function PATCH(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const body = await request.text();
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'PATCH',
    body: body || undefined,
    headers: {
      'Content-Type': request.headers.get('Content-Type') ?? 'application/json',
      Authorization: `Bearer ${authResult.token}`,
    },
  });
  return forwardResponse(res);
}

export async function DELETE(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${authResult.token}` },
  });
  return forwardResponse(res);
}
```

## A2. Missing Edit Pages

### A2.1 Create shared types file
**Create** `apps/web/src/lib/types.ts`:

```typescript
export interface Region {
  id: string; name: string; code: string | null;
  created_at: string; updated_at: string;
}

export interface Entity {
  id: string; region_id: string; name: string; code: string | null;
  created_at: string; updated_at: string;
  regions?: { id: string; name: string; code: string | null };
}

export interface Project {
  id: string; entity_id: string; name: string; code: string | null;
  created_at: string; updated_at: string;
  entities?: { id: string; name: string; code: string | null; region_id: string };
}

export interface CounterpartyContact {
  id: string; counterparty_id: string; name: string;
  email: string | null; role: string | null; is_signer: boolean;
  created_at: string; updated_at: string;
}

export interface Counterparty {
  id: string; legal_name: string; registration_number: string | null;
  address: string | null; jurisdiction: string | null;
  status: 'Active' | 'Suspended' | 'Blacklisted';
  status_reason: string | null; supporting_document_ref: string | null;
  preferred_language: string;
  created_at: string; updated_at: string;
  counterparty_contacts?: CounterpartyContact[];
}

export interface Contract {
  id: string; region_id: string; entity_id: string; project_id: string;
  counterparty_id: string; contract_type: 'Commercial' | 'Merchant';
  title: string | null; workflow_state: string; signing_status: string | null;
  storage_path: string | null; file_name: string | null; file_version: number;
  created_at: string; updated_at: string;
  created_by: string | null; updated_by: string | null;
  regions?: { id: string; name: string };
  entities?: { id: string; name: string };
  projects?: { id: string; name: string };
  counterparties?: { id: string; legal_name: string; status: string };
}
```

Update all existing list components and detail pages to import from `@/lib/types` instead of defining inline interfaces.

### A2.2 Create entity edit page
**Create** `apps/web/src/app/(dashboard)/entities/[id]/page.tsx` and `apps/web/src/app/(dashboard)/entities/edit-entity-form.tsx`.

Follow the exact same pattern as the existing `regions/[id]/page.tsx` and `regions/edit-region-form.tsx`:
- Fetch entity via `GET /api/ccrs/entities/${id}`
- Show region name (read-only), name (editable), code (editable)
- Submit via `PATCH /api/ccrs/entities/${id}`
- Handle loading, error, and success (redirect to `/entities`)
- Add `.catch()` error handling on fetch

### A2.3 Create project edit page
**Create** `apps/web/src/app/(dashboard)/projects/[id]/page.tsx` and `apps/web/src/app/(dashboard)/projects/edit-project-form.tsx`.

Same pattern:
- Fetch project via `GET /api/ccrs/projects/${id}`
- Show entity name (read-only), name (editable), code (editable)
- Submit via `PATCH /api/ccrs/projects/${id}`

### A2.4 Add counterparty edit form + status management
**Rewrite** `apps/web/src/app/(dashboard)/counterparties/counterparty-detail-page.tsx`:

Replace the current read-only view with:
1. **Edit mode toggle:** "Edit" button switches between view and edit modes
2. **Edit form:** Fields for `legal_name`, `registration_number`, `address`, `jurisdiction`, `preferred_language`. Submit via `PATCH /api/ccrs/counterparties/${id}`.
3. **Status management section** (always visible below the form):
   - Current status badge
   - Dropdown to select new status: Active / Suspended / Blacklisted
   - Required "Reason" textarea
   - Optional "Supporting Document Reference" input
   - "Change Status" button that calls `PATCH /api/ccrs/counterparties/${id}/status` with `{status, reason, supporting_document_ref}`
   - Confirmation dialog before status change
4. **Contacts section:** List contacts with name/email/role/is_signer. "Add Contact" form that calls `POST /api/ccrs/counterparties/${id}/contacts`. Delete button per contact.

## A3. Search/Filter UI and Audit

### A3.1 Contract search and filter UI
**Rewrite** `apps/web/src/app/(dashboard)/contracts/contracts-list.tsx`:

Replace the simple card grid with:
1. **Search bar:** Text input for full-text search (maps to `?q=` query param)
2. **Filter row:** Dropdowns for Region, Entity, Project (cascading), Contract Type (Commercial/Merchant), Workflow State
3. **Results table:** Use the shadcn `Table` component (already installed at `components/ui/table.tsx`). Columns: Title, Type, Status, Region, Entity, Counterparty, Created At, Actions (View)
4. **Pagination:** Previous/Next buttons with offset tracking. Show "Showing X–Y of Z" using the `X-Total-Count` response header.
5. **Loading and error states**

Fetch with all filters: `GET /api/ccrs/contracts?q=&regionId=&entityId=&projectId=&contractType=&workflowState=&limit=25&offset=0`

### A3.2 Audit trail UI
**Rewrite** `apps/web/src/app/(dashboard)/audit/page.tsx`:

Replace the placeholder with a functional page:
1. **Filter section:** Date range inputs (from/to), resource type dropdown, actor ID input
2. **Results table:** Using shadcn Table. Columns: Timestamp, Action, Resource Type, Resource ID, Actor Email, IP Address, Details (expandable JSON)
3. **Export button:** Calls `GET /api/ccrs/audit/export?from=...&to=...&resourceType=...&actorId=...` and downloads the JSON response as a `.json` file (or formats as CSV)
4. **Pagination:** Same pattern as contracts

## A4. UX and Cleanup Fixes

### A4.1 Fix nav sub-route highlighting
**File:** `apps/web/src/components/app-nav.tsx`

Change the active check from `pathname === href` to:
```typescript
const isActive = href === '/' ? pathname === '/' : pathname === href || pathname.startsWith(href + '/');
```

### A4.2 Replace `<a>` with `<Link>` on dashboard
**File:** `apps/web/src/app/(dashboard)/page.tsx`

Import `Link` from `next/link` and replace all `<a href="...">` with `<Link href="...">`.

### A4.3 Fix auth provider import
**File:** `apps/web/src/auth.ts`

Change `import AzureADProvider from 'next-auth/providers/azure-ad'` to:
```typescript
import MicrosoftEntraID from 'next-auth/providers/microsoft-entra-id';
```

And update the provider usage from `AzureADProvider({...})` to `MicrosoftEntraID({...})`.

### A4.4 Delete dead code
**Delete** `apps/web/src/lib/api.ts` — it is never imported anywhere.

### A4.5 Redirect authenticated users from /login
**File:** `apps/web/src/middleware.ts`

Add the reverse check:
```typescript
export default auth((req) => {
  const isLoggedIn = !!req.auth;
  const isLogin = req.nextUrl.pathname.startsWith('/login');
  if (!isLoggedIn && !isLogin) {
    return Response.redirect(new URL('/login', req.nextUrl));
  }
  if (isLoggedIn && isLogin) {
    return Response.redirect(new URL('/', req.nextUrl));
  }
  return undefined;
});
```

### A4.6 Add error handling to all list fetches
In every list component (`regions-list.tsx`, `entities-list.tsx`, `projects-list.tsx`, `counterparties-list.tsx`), change the fetch pattern from:

```typescript
fetch('/api/ccrs/...').then(r => r.json()).then(setData).finally(() => setLoading(false));
```

To:

```typescript
fetch('/api/ccrs/...')
  .then(r => { if (!r.ok) throw new Error(`${r.status}`); return r.json(); })
  .then(setData)
  .catch(e => setError(e.message))
  .finally(() => setLoading(false));
```

Add `const [error, setError] = useState<string | null>(null);` state and render error message when set.

---

# PART B: PHASE 1a-fix — API POLISH

## B1. Fix Azure AD JWKS verification
**File:** `apps/api/app/auth/jwt.py`

The current RS256 path incorrectly uses `settings.azure_ad_client_secret` as the decoding key. RS256 requires the public key from Microsoft's JWKS endpoint.

Replace the Azure AD section with proper JWKS fetching:

```python
import httpx
from jose import jwt, jwk
from functools import lru_cache

@lru_cache(maxsize=1)
def _get_azure_jwks(issuer: str) -> dict:
    """Fetch and cache JWKS from Azure AD."""
    jwks_url = f"{issuer}/.well-known/openid-configuration"
    config = httpx.get(jwks_url).json()
    jwks = httpx.get(config["jwks_uri"]).json()
    return jwks

def decode_token(token: str) -> dict:
    # Try HS256 (NextAuth) first
    try:
        return jwt.decode(token, settings.jwt_secret, algorithms=["HS256"], options={"verify_aud": False})
    except (JWTError, ExpiredSignatureError):
        pass

    # Try Azure AD RS256 if configured
    if settings.azure_ad_client_id and settings.azure_ad_issuer:
        try:
            jwks = _get_azure_jwks(settings.azure_ad_issuer)
            # Get the signing key from the unverified header
            unverified = jwt.get_unverified_header(token)
            key = next(k for k in jwks["keys"] if k["kid"] == unverified["kid"])
            return jwt.decode(
                token, key, algorithms=["RS256"],
                audience=settings.azure_ad_client_id,
                issuer=settings.azure_ad_issuer,
            )
        except (JWTError, ExpiredSignatureError, StopIteration):
            pass

    raise HTTPException(status_code=401, detail="Invalid or expired token")
```

This requires `httpx` (already in requirements.txt).

## B2. Populate user_id in logging middleware
**File:** `apps/api/app/auth/dependencies.py`

In the `get_current_user` function, after extracting the user, set `request.state.user_id`:

```python
async def get_current_user(request: Request, credentials: HTTPAuthorizationCredentials = Depends(security)) -> CurrentUser:
    payload = decode_token(credentials.credentials)
    user_id = payload.get("oid") or payload.get("sub")
    if not user_id:
        raise HTTPException(status_code=401, detail="Invalid token: no subject")
    user = CurrentUser(
        id=user_id,
        email=payload.get("email") or payload.get("preferred_username"),
        roles=payload.get("roles", []),
        ip_address=request.client.host if request.client else None,
    )
    request.state.user_id = user.id  # <-- ADD THIS LINE for logging middleware
    return user
```

## B3. Add response models and X-Total-Count consistency
Add `X-Total-Count` header to contracts list and signing_authority list endpoints (matching regions/entities/projects/counterparties).

## B4. Comprehensive test suite
**Expand the test suite in `apps/api/tests/`.**

**File:** `apps/api/tests/conftest.py`
Add dependency overrides for `get_supabase` and `get_current_user`:

```python
import pytest
from unittest.mock import MagicMock, AsyncMock
from fastapi.testclient import TestClient
from app.main import app
from app.deps import get_supabase
from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser

@pytest.fixture
def mock_supabase():
    """Create a mock Supabase client with chainable query builder."""
    client = MagicMock()
    # Set up chainable table().select().eq().execute() pattern
    # ... (implement chainable mocks for each table operation)
    return client

@pytest.fixture
def test_user():
    return CurrentUser(id="test-1", email="test@example.com", roles=["System Admin"], ip_address="127.0.0.1")

@pytest.fixture
def authed_client(mock_supabase, test_user):
    """TestClient with mocked auth and Supabase."""
    app.dependency_overrides[get_supabase] = lambda: mock_supabase
    app.dependency_overrides[get_current_user] = lambda: test_user
    client = TestClient(app)
    yield client
    app.dependency_overrides.clear()

@pytest.fixture
def unauthed_client():
    """TestClient without auth overrides."""
    return TestClient(app)
```

**Create test files for each module.** Each should test:

1. **`tests/test_regions.py`** — Create (201), list (200 + X-Total-Count), get (200), update (200), delete (200), get non-existent (404), create requires auth (401), create requires System Admin role (403)
2. **`tests/test_entities.py`** — Same CRUD tests + regionId filter on list
3. **`tests/test_projects.py`** — Same CRUD tests + entityId filter on list
4. **`tests/test_counterparties.py`** — CRUD + duplicate detection (substring match + exact reg number) + status change (persists reason + supporting_document_ref) + status change requires Legal role
5. **`tests/test_contracts.py`** — Upload (201 with PDF), upload rejected for non-PDF (400), upload blocked for Blacklisted counterparty (400), search with filters, search with full-text q, update blocked for executed state (400), delete blocked for archived state (400), download-url audits access
6. **`tests/test_signing_authority.py`** — CRUD + requires System Admin
7. **`tests/test_counterparty_contacts.py`** — CRUD under counterparty
8. **`tests/test_audit.py`** — Export requires role, date filter validation, resource query
9. **`tests/test_auth.py`** — Valid HS256 token decodes, expired token rejected, malformed token rejected

## B5. Fix CI pipeline
**File:** `.github/workflows/ci.yml`

Replace the backend job with Python:

```yaml
backend:
  name: Backend (API)
  runs-on: ubuntu-latest
  defaults:
    run:
      working-directory: apps/api
  steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-python@v5
      with:
        python-version: "3.12"
        cache: pip
        cache-dependency-path: apps/api/requirements.txt
    - name: Install dependencies
      run: pip install -r requirements.txt
    - name: Run tests
      run: pytest tests/ -v --tb=short
```

Remove `continue-on-error: true` from ALL steps in ALL jobs (frontend, backend, root).

---

# PART C: PHASE 1b DATABASE SCHEMA

Create migration `supabase/migrations/20260217000002_phase1b_schema.sql`:

```sql
-- =============================================================================
-- Phase 1b: Workflows, Signing, Amendments
-- =============================================================================

-- WikiContracts: template/precedent library (Epic 4/10)
CREATE TABLE IF NOT EXISTS wiki_contracts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  category TEXT,
  region_id UUID REFERENCES regions(id) ON DELETE SET NULL,
  version INTEGER NOT NULL DEFAULT 1,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'review', 'published', 'deprecated')),
  storage_path TEXT,
  file_name TEXT,
  description TEXT,
  created_by TEXT,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_wiki_contracts_status ON wiki_contracts(status);
CREATE INDEX IF NOT EXISTS idx_wiki_contracts_region ON wiki_contracts(region_id);

-- Workflow templates: versioned workflow definitions (Epic 4)
CREATE TABLE IF NOT EXISTS workflow_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  contract_type TEXT NOT NULL CHECK (contract_type IN ('Commercial', 'Merchant')),
  region_id UUID REFERENCES regions(id) ON DELETE SET NULL,
  entity_id UUID REFERENCES entities(id) ON DELETE SET NULL,
  project_id UUID REFERENCES projects(id) ON DELETE SET NULL,
  version INTEGER NOT NULL DEFAULT 1,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'deprecated')),
  stages JSONB NOT NULL DEFAULT '[]',
  validation_errors JSONB,
  created_by TEXT,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(name, version)
);

CREATE INDEX IF NOT EXISTS idx_workflow_templates_status ON workflow_templates(status);
CREATE INDEX IF NOT EXISTS idx_workflow_templates_contract_type ON workflow_templates(contract_type);

-- Workflow instances: active workflow bound to a contract (Epic 4)
CREATE TABLE IF NOT EXISTS workflow_instances (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  template_id UUID NOT NULL REFERENCES workflow_templates(id) ON DELETE RESTRICT,
  template_version INTEGER NOT NULL,
  current_stage TEXT NOT NULL,
  state TEXT NOT NULL DEFAULT 'active' CHECK (state IN ('active', 'completed', 'cancelled')),
  started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  completed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_workflow_instances_contract ON workflow_instances(contract_id) WHERE state = 'active';
CREATE INDEX IF NOT EXISTS idx_workflow_instances_state ON workflow_instances(state);

-- Workflow stage actions: approvals, rejections, rework (Epic 4)
CREATE TABLE IF NOT EXISTS workflow_stage_actions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  instance_id UUID NOT NULL REFERENCES workflow_instances(id) ON DELETE CASCADE,
  stage_name TEXT NOT NULL,
  action TEXT NOT NULL CHECK (action IN ('approve', 'reject', 'rework', 'skip')),
  actor_id TEXT,
  actor_email TEXT,
  comment TEXT,
  artifacts JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_stage_actions_instance ON workflow_stage_actions(instance_id);

-- Boldsign envelopes: signing tracking (Epic 9)
CREATE TABLE IF NOT EXISTS boldsign_envelopes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  boldsign_document_id TEXT UNIQUE,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'viewed', 'partially_signed', 'completed', 'declined', 'expired', 'voided')),
  signing_order TEXT NOT NULL DEFAULT 'sequential' CHECK (signing_order IN ('parallel', 'sequential')),
  signers JSONB NOT NULL DEFAULT '[]',
  webhook_payload JSONB,
  sent_at TIMESTAMPTZ,
  completed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_boldsign_contract ON boldsign_envelopes(contract_id);
CREATE INDEX IF NOT EXISTS idx_boldsign_document ON boldsign_envelopes(boldsign_document_id);

-- Contract links: amendments, renewals, side letters (Epic 15)
CREATE TABLE IF NOT EXISTS contract_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  parent_contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  child_contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  link_type TEXT NOT NULL CHECK (link_type IN ('amendment', 'renewal', 'side_letter', 'addendum')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(parent_contract_id, child_contract_id)
);

CREATE INDEX IF NOT EXISTS idx_contract_links_parent ON contract_links(parent_contract_id);
CREATE INDEX IF NOT EXISTS idx_contract_links_child ON contract_links(child_contract_id);

-- Contract key dates: extracted or manual key dates (Epic 2 extended)
CREATE TABLE IF NOT EXISTS contract_key_dates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  date_type TEXT NOT NULL,
  date_value DATE NOT NULL,
  description TEXT,
  reminder_days INTEGER[],
  is_verified BOOLEAN DEFAULT false,
  verified_by TEXT,
  verified_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_key_dates_contract ON contract_key_dates(contract_id);
CREATE INDEX IF NOT EXISTS idx_key_dates_value ON contract_key_dates(date_value);

-- Merchant agreement structured inputs (Epic 10)
CREATE TABLE IF NOT EXISTS merchant_agreement_inputs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  template_id UUID REFERENCES wiki_contracts(id) ON DELETE SET NULL,
  vendor_name TEXT NOT NULL,
  merchant_fee TEXT,
  region_terms JSONB,
  generated_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add SharePoint fields to contracts
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS sharepoint_url TEXT;
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS sharepoint_version TEXT;

-- Add parent_contract_id for quick amendment/renewal lookups
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS parent_contract_id UUID REFERENCES contracts(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_contracts_parent ON contracts(parent_contract_id) WHERE parent_contract_id IS NOT NULL;

-- Apply updated_at triggers to new tables
DO $$
DECLARE
  t TEXT;
BEGIN
  FOR t IN SELECT unnest(ARRAY[
    'wiki_contracts', 'workflow_templates', 'workflow_instances',
    'boldsign_envelopes', 'contract_key_dates'
  ])
  LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS set_updated_at ON %I', t);
    EXECUTE format(
      'CREATE TRIGGER set_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()',
      t
    );
  END LOOP;
END;
$$;
```

---

# PART D: PHASE 1b BACKEND MODULES

## D1. WikiContracts Module (Epic 4/10)

**Create** `apps/api/app/wiki_contracts/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/wiki-contracts` | JWT | System Admin, Legal |
| `GET` | `/wiki-contracts` | JWT | Any |
| `GET` | `/wiki-contracts/{id}` | JWT | Any |
| `PATCH` | `/wiki-contracts/{id}` | JWT | System Admin, Legal |
| `PATCH` | `/wiki-contracts/{id}/publish` | JWT | System Admin |
| `DELETE` | `/wiki-contracts/{id}` | JWT | System Admin |
| `POST` | `/wiki-contracts/{id}/upload` | JWT | System Admin, Legal |
| `GET` | `/wiki-contracts/{id}/download-url` | JWT | Any |

**Schemas:**
- `CreateWikiContractInput`: `name` (str, required), `category` (str, optional), `region_id` (UUID, optional), `description` (str, optional)
- `UpdateWikiContractInput`: all fields optional
- Status transitions: draft → review → published → deprecated. Only System Admin can publish.

**Query params on list:** `status`, `regionId`, `category`, `limit`, `offset`

**File upload:** `POST /wiki-contracts/{id}/upload` accepts multipart file (PDF/DOCX), stores in Supabase Storage bucket `wiki-contracts` (create this bucket in the migration or at app startup).

Audit all mutations.

## D2. Workflow Engine Module (Epic 4)

**Create** `apps/api/app/workflows/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`, `state_machine.py`.

### Workflow Template Endpoints

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/workflow-templates` | JWT | System Admin |
| `GET` | `/workflow-templates` | JWT | Any |
| `GET` | `/workflow-templates/{id}` | JWT | Any |
| `PATCH` | `/workflow-templates/{id}` | JWT | System Admin |
| `POST` | `/workflow-templates/{id}/publish` | JWT | System Admin |
| `DELETE` | `/workflow-templates/{id}` | JWT | System Admin |

**Template schemas:**

`WorkflowStage` (Pydantic model for each element in the `stages` JSONB array):
```python
class WorkflowStage(BaseModel):
    name: str
    type: Literal["approval", "signing", "review", "draft"]
    description: str | None = None
    owners: list[str] = []                  # Role names or user IDs
    approvers: list[str] = []               # Role names or user IDs
    required_artifacts: list[str] = []       # e.g. ["signed_copy", "board_resolution"]
    allowed_transitions: list[str] = []      # Stage names this can transition to
    sla_hours: int | None = None             # Max hours before SLA breach
    signing_order: Literal["parallel", "sequential"] | None = None
```

**Validation on publish:**
1. Must have at least one `approval` stage
2. Must have at least one `signing` stage (unless explicitly flagged as no-sign workflow)
3. All `allowed_transitions` reference valid stage names
4. No orphan stages (every stage is reachable from the first stage)
5. First stage cannot be `signing` type
6. Signing authority: at least one entity/project with signing authority rules must be mapped

### Workflow Instance Endpoints

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/contracts/{contract_id}/workflow` | JWT | System Admin, Legal, Commercial |
| `GET` | `/contracts/{contract_id}/workflow` | JWT | Any |
| `POST` | `/workflow-instances/{id}/stages/{stage_name}/action` | JWT | Depends on stage config |
| `GET` | `/workflow-instances/{id}/history` | JWT | Any |

**`POST /contracts/{id}/workflow`** — Start a workflow instance:
- Body: `{template_id}` — the published workflow template to use
- Creates a `workflow_instances` record with `current_stage` set to the first stage
- Updates contract `workflow_state` to the first stage name
- Returns the instance with stages and current position

**`POST /workflow-instances/{id}/stages/{stage_name}/action`** — Take action on a stage:
- Body: `{action: "approve"|"reject"|"rework", comment, artifacts}`
- Validate: the `stage_name` matches `current_stage`
- Validate: the actor has permission (is listed in the stage's `owners` or `approvers`)
- If action is `approve`: advance to next stage per `allowed_transitions`. If it's the last stage, mark instance as `completed`.
- If action is `reject` or `rework`: move to the specified rework target stage
- Record the action in `workflow_stage_actions`
- Update contract `workflow_state` to the new stage name
- At signing stage: check signing authority before allowing `approve`
- Audit log all actions

### state_machine.py

Implement the workflow state machine logic:
```python
def validate_template(stages: list[WorkflowStage]) -> list[str]:
    """Returns list of validation error strings (empty = valid)."""

def get_next_stage(stages: list[WorkflowStage], current: str, action: str) -> str | None:
    """Given current stage and action, return the next stage name or None if workflow is complete."""

def can_actor_act(stage: WorkflowStage, actor: CurrentUser, signing_authority: list[dict]) -> bool:
    """Check if the actor has permission to act on this stage."""
```

## D3. Boldsign Integration Module (Epic 9)

**Create** `apps/api/app/boldsign/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

**Add** `BOLDSIGN_API_KEY` and `BOLDSIGN_API_URL` to `config.py` Settings (optional, default None).

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/contracts/{id}/send-to-sign` | JWT | System Admin, Legal, Commercial |
| `GET` | `/contracts/{id}/signing-status` | JWT | Any |
| `POST` | `/webhooks/boldsign` | None (verify Boldsign signature) | N/A |

**Send to sign flow:**
1. Validate contract is at a `signing` workflow stage
2. Check signing authority for the actor
3. Build Boldsign send request with signers from counterparty contacts (where `is_signer = true`) and internal signers from signing authority
4. Create `boldsign_envelopes` record
5. Call Boldsign API to send
6. Update contract `signing_status` to `sent`
7. Audit log

**Webhook handler:**
1. Verify Boldsign webhook signature (header-based HMAC or IP whitelist)
2. Parse event: `viewed`, `signed`, `completed`, `declined`, `expired`
3. Update `boldsign_envelopes` status
4. Update contract `signing_status`
5. On `completed`: download executed PDF from Boldsign, store in Supabase Storage, update contract `workflow_state` to `executed`, lock contract as immutable
6. Audit log all events

## D4. Contract Links Module (Epic 15)

**Create** `apps/api/app/contract_links/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/contracts/{id}/amendments` | JWT | System Admin, Legal, Commercial |
| `POST` | `/contracts/{id}/renewals` | JWT | System Admin, Legal, Commercial |
| `POST` | `/contracts/{id}/side-letters` | JWT | System Admin, Legal, Commercial |
| `GET` | `/contracts/{id}/linked` | JWT | Any |

**Amendment:** Creates a new contract record inheriting `region_id`, `entity_id`, `project_id`, `counterparty_id` from the parent. Creates a `contract_links` record with `link_type = 'amendment'`. The amendment gets its own workflow instance.

**Renewal:** Body includes `{type: "extension"|"new_version"}`.
- Extension: updates the parent contract's key dates
- New version: creates a new contract linked to the predecessor

**Side letter:** Creates a new contract linked with `link_type = 'side_letter'`.

**`GET /contracts/{id}/linked`** returns all linked contracts grouped by type.

Update the contract detail endpoint (`GET /contracts/{id}`) to include linked contracts in the response.

## D5. Key Dates Module

**Create** `apps/api/app/key_dates/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/contracts/{id}/key-dates` | JWT | System Admin, Legal, Commercial |
| `GET` | `/contracts/{id}/key-dates` | JWT | Any |
| `PATCH` | `/key-dates/{id}` | JWT | System Admin, Legal |
| `PATCH` | `/key-dates/{id}/verify` | JWT | Legal |
| `DELETE` | `/key-dates/{id}` | JWT | System Admin |

**Schema:** `date_type` (str: effective_date, expiry_date, renewal_notice, payment_due, custom), `date_value` (date), `description` (str), `reminder_days` (int[]: e.g. [90, 60, 30])

Audit all mutations.

## D6. Merchant Agreement Generation (Epic 10)

**Create** `apps/api/app/merchant_agreements/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

**Add** `python-docx-template` to `requirements.txt`.

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/merchant-agreements/generate` | JWT | System Admin, Legal, Commercial |
| `GET` | `/tito/validate` | API Key | N/A |

**Generate flow:**
1. Accept: `{template_id, vendor_name, merchant_fee, region_id, entity_id, project_id, counterparty_id, region_terms}`
2. Fetch the WikiContracts template file from Supabase Storage
3. Render with `python-docx-template` (or `docxtpl`): replace `{{vendor_name}}`, `{{merchant_fee}}`, etc.
4. Store generated document in Supabase Storage
5. Create contract record (type: Merchant, workflow_state: draft)
6. Store structured inputs in `merchant_agreement_inputs`
7. Return the created contract

**TiTo validation API:**
- `GET /tito/validate?vendor=&entity_id=&region_id=&project_id=`
- API key auth (not JWT) — validate `X-API-Key` header against `TITO_API_KEY` env var
- Query contracts table for matching Merchant Agreement with `signing_status = 'completed'`
- Return: `{valid: bool, contract_id, signed_at, status}`
- Target: p95 < 500ms
- Audit log all calls

---

# PART E: PHASE 1b FRONTEND

## E1. Workflow Builder Page

**Install** `reactflow` in `apps/web`:
```bash
cd apps/web && npm install reactflow
```

**Create** `apps/web/src/app/(dashboard)/workflows/` with:
- `page.tsx` — List workflow templates
- `new/page.tsx` — Create new workflow template
- `[id]/page.tsx` — Edit workflow template (visual builder)
- `workflow-builder.tsx` — React Flow canvas with:
  - Drag-and-drop stage nodes (approval, signing, review, draft)
  - Edge connections between stages (allowed transitions)
  - Side panel for stage configuration (owners, SLA, artifacts)
  - "Validate" button that calls backend validation
  - "Publish" button (blocked until validation passes)
- `workflow-templates-list.tsx` — Table of templates with status, version, actions

**Add** "Workflows" link to `app-nav.tsx`.

## E2. Contract Workflow UI

Enhance the contract detail page (`contracts/contract-detail.tsx`):
- **Workflow progress bar:** Visual indicator showing all stages and current position
- **"Start Workflow" button:** Select a published template and start an instance
- **Stage action buttons:** "Approve", "Reject", "Rework" with comment dialog
- **Workflow history tab:** Table of all stage actions (who, what, when, comment)
- **"Send to Sign" button:** Only visible at signing stage; opens signer configuration dialog

## E3. WikiContracts Library Page

**Create** `apps/web/src/app/(dashboard)/wiki-contracts/` with:
- `page.tsx` — List templates/precedents with status filter
- `new/page.tsx` — Upload new template
- `[id]/page.tsx` — View/edit template details, upload new version, publish/deprecate

## E4. Amendments, Renewals & Side Letters

Enhance contract detail page:
- **"Linked Documents" tab** showing amendments, renewals, side letters in separate sections
- **"Create Amendment" button** — form that inherits classification, creates linked contract
- **"Renew Contract" button** — modal with extension vs. new version choice
- **"Add Side Letter" button** — upload form for supplementary agreement
- **Key Dates tab** — list key dates with add/edit/verify/delete actions

## E5. Merchant Agreement Generator

**Create** `apps/web/src/app/(dashboard)/merchant-agreements/` with:
- `page.tsx` — "Generate New Merchant Agreement" form
- Form fields: template selector (from WikiContracts), vendor name, merchant fee, region, entity, project, counterparty, region-specific terms
- "Generate" button → calls API → shows generated contract detail
- "Send to Sign" button (reuses the workflow signing UI)

## E6. Navigation Update

**Update** `apps/web/src/components/app-nav.tsx` to add:
- "Workflows" → `/workflows`
- "Templates" → `/wiki-contracts`
- "Merchant Agreements" → `/merchant-agreements` (or as a filter on contracts)

---

# PART F: PHASE 1b TESTS

**Create test files for all new backend modules:**

1. **`tests/test_wiki_contracts.py`** — CRUD, publish flow (draft → review → published), upload file, download URL
2. **`tests/test_workflow_templates.py`** — CRUD, validation (missing approval stage, missing signing stage, orphan nodes), publish blocked on invalid, version increment on publish
3. **`tests/test_workflow_instances.py`** — Start workflow, approve stage, reject + rework, complete workflow, signing authority check at signing stage, immutable state after completion
4. **`tests/test_workflow_state_machine.py`** — Unit tests for `validate_template()`, `get_next_stage()`, `can_actor_act()`
5. **`tests/test_boldsign.py`** — Send to sign (validates stage + authority), webhook handler (status updates, executed copy download)
6. **`tests/test_contract_links.py`** — Create amendment (inherits classification), create renewal (extension + new version), create side letter, list linked contracts
7. **`tests/test_key_dates.py`** — CRUD, verify, reminder_days array handling
8. **`tests/test_merchant_agreements.py`** — Generate from template, TiTo validation (valid + invalid + logged)

---

# Completion Checklist

After all changes:
1. [ ] `cd apps/api && pytest tests/ -v` — all tests pass
2. [ ] `cd apps/web && npm run build` — no errors
3. [ ] New migration applied to Supabase
4. [ ] All Phase 1a frontend bugs resolved (11/11)
5. [ ] WikiContracts module working
6. [ ] Workflow engine with state machine working
7. [ ] Visual workflow builder in React Flow working
8. [ ] Boldsign integration stubbed (functional when API key configured)
9. [ ] Amendment/renewal/side letter linking working
10. [ ] Key dates management working
11. [ ] Merchant Agreement generation working
12. [ ] TiTo validation API working
13. [ ] All new endpoints visible in Swagger at `/docs`
