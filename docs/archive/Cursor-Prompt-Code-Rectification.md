# Cursor Prompt — CCRS Code Rectification (Post Phase 1c)

**Copy everything below this line into Cursor as the prompt.**

---

## Context

A comprehensive code audit of the CCRS codebase revealed **critical issues** that must be fixed before proceeding. The most severe problem is that **25+ Python files and 1 SQL migration have their entire contents duplicated** (pasted twice in the same file), causing duplicate class/function definitions and fatal duplicate route registration in FastAPI.

This prompt addresses all issues in priority order. **Fix every item in order.**

After each section, verify:
- Backend: `cd apps/api && python -m py_compile app/main.py && pytest tests/ -v`
- Frontend: `cd apps/web && npm run build`

---

# SECTION 1: REMOVE ALL DUPLICATE FILE CONTENTS (CRITICAL)

The following files contain their entire content duplicated. **For each file, delete the second (duplicate) copy of all content**, keeping only the first copy. Where the two copies differ (noted below), keep the BETTER version as specified.

## 1.1 Backend Python Files — Remove Second Copy

For each file below, the content after the midpoint is a duplicate. Delete everything from the second copy onward.

**Simple duplicates (both copies identical — keep first half only):**

1. `apps/api/app/scheduler.py` — 102 lines → should be ~51
2. `apps/api/app/ai/schemas.py` — 144 lines → should be ~72
3. `apps/api/app/ai/config.py` — 20 lines → should be ~10
4. `apps/api/app/ai/messages_client.py` — 102 lines → should be ~51
5. `apps/api/app/ai/agent_client.py` — 299 lines → should be ~150
6. `apps/api/app/ai/mcp_tools.py` — 268 lines → should be ~134
7. `apps/api/app/ai/workflow_generator.py` — 270 lines → should be ~135
8. `apps/api/app/ai_analysis/schemas.py` — 39 lines → should be ~20
9. `apps/api/app/obligations/schemas.py` — 42 lines → should be ~21
10. `apps/api/app/obligations/service.py` — 152 lines → should be ~76
11. `apps/api/app/reminders/schemas.py` — 45 lines → should be ~23
12. `apps/api/app/escalation/schemas.py` — 34 lines → should be ~17
13. `apps/api/app/notifications/service.py` — 164 lines → should be ~85

**Duplicates with differences (keep the BETTER version as noted):**

14. `apps/api/app/obligations/router.py` — 176 lines → ~88. Duplicate routes will crash FastAPI. Keep first copy.

15. `apps/api/app/reminders/router.py` — 132 lines → ~66. Keep first copy.

16. `apps/api/app/reminders/service.py` — 268 lines → ~131. Second copy uses string concatenation instead of f-strings for notification body. **Keep first copy** (uses f-strings).

17. `apps/api/app/escalation/service.py` — 378 lines → ~189. Keep first copy.

18. `apps/api/app/escalation/router.py` — 188 lines → ~94. Keep first copy.

19. `apps/api/app/notifications/router.py` — 46 lines → ~23. Keep first copy.

20. `apps/api/app/reports/service.py` — 226 lines → ~115. **IMPORTANT**: The second copy of `expiry_horizon()` is MISSING the `region_id` filter. **Keep the FIRST copy** which has the filter.

21. `apps/api/app/reports/router.py` — 90 lines → ~45. Keep first copy.

22. `apps/api/app/contract_languages/schemas.py` — 12 lines → ~6. Keep first copy.

23. `apps/api/app/contract_languages/service.py` — 232 lines → ~119. The second copy adds a `download_url` field and `_get_signed_url()` helper. **Merge**: keep the first copy's structure but ADD the `_get_signed_url()` helper and `download_url` enrichment from the second copy into `list_languages()`.

24. `apps/api/app/contract_languages/router.py` — 122 lines → ~68. Second copy is truncated (missing download-url endpoint). **Keep the FIRST copy**.

25. `apps/api/app/ai_analysis/router.py` — 141 lines → ~70. Second copy adds `body: VerifyFieldInput` parameter to `verify_field()`. **Keep the FIRST copy** but ADD the `body` parameter to `verify_field()` — it should accept the `VerifyFieldInput` body.

## 1.2 SQL Migration — Remove Duplicate

26. `supabase/migrations/20260217000003_phase1c_schema.sql` — 368 lines → should be ~184. The entire migration is duplicated. Keep the first half only. `IF NOT EXISTS` prevents errors but this must be cleaned up.

---

# SECTION 2: FIX STRUCTLOG STARTUP CRASH (CRITICAL)

**File:** `apps/api/app/main.py`

The call `structlog.make_filtering_bound_logger(settings.log_level.upper())` passes a **string** ("INFO") but the function expects an **integer** log level. This causes a TypeError at startup.

**Fix:** Change the structlog configuration:

```python
import logging

structlog.configure(
    processors=[
        structlog.contextvars.merge_contextvars,
        structlog.processors.add_log_level,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.dev.ConsoleRenderer()
        if settings.log_level == "debug"
        else structlog.processors.JSONRenderer(),
    ],
    wrapper_class=structlog.make_filtering_bound_logger(
        logging.getLevelName(settings.log_level.upper())
    ),
)
```

---

# SECTION 3: FIX ASYNC/SYNC MISMATCH IN AI CLIENTS (HIGH)

The Anthropic Messages API calls use `anthropic.Anthropic` (sync client) inside `async` functions. This blocks the event loop.

## 3.1 Fix `apps/api/app/ai/messages_client.py`

Change `anthropic.Anthropic` to `anthropic.AsyncAnthropic` and `client.messages.create` to `await client.messages.create`:

```python
async def analyze_summary(contract_text: str) -> tuple[SummaryResult, AnalysisUsage]:
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    start = time.monotonic()

    response = await client.messages.create(
        model=settings.ai_model,
        max_tokens=2048,
        system=SUMMARY_SYSTEM_PROMPT,
        messages=[{"role": "user", "content": contract_text[:100_000]}],
    )
    # ... rest stays the same
```

## 3.2 Fix `apps/api/app/ai/agent_client.py`

Same change — use `AsyncAnthropic` and `await`:

```python
async def analyze_complex(...) -> tuple[dict, AnalysisUsage]:
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    # ...
    for _ in range(10):
        response = await client.messages.create(...)
        # ... rest of loop stays the same
```

## 3.3 Fix `apps/api/app/ai/workflow_generator.py`

Same change:

```python
async def generate_workflow(...) -> dict:
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    # ...
    for _ in range(10):
        response = await client.messages.create(...)
        # ...
    # For the retry:
    retry = await client.messages.create(...)
```

---

# SECTION 4: FIX WORKFLOW STATE MACHINE BUG (HIGH)

**File:** `apps/api/app/workflows/state_machine.py`

`get_next_stage()` returns the same `allowed_transitions[0]` for BOTH "approve" AND "reject"/"rework". Reject/rework should go backward, not forward.

**Fix:** Change the function to handle reject/rework properly:

```python
def get_next_stage(stages: list[WorkflowStage], current: str, action: str) -> str | None:
    stage = next((s for s in stages if s.name == current), None)
    if not stage:
        return None

    if action == "approve":
        # Move forward to the next stage
        return stage.allowed_transitions[0] if stage.allowed_transitions else None

    if action in ("reject", "rework"):
        # Move backward: find the previous stage in the ordered list
        stage_names = [s.name for s in stages]
        current_idx = stage_names.index(current) if current in stage_names else 0
        if current_idx > 0:
            return stage_names[current_idx - 1]
        # Already at first stage — stay at current
        return current

    return None
```

---

# SECTION 5: FIX SECURITY ISSUES (HIGH)

## 5.1 TiTo API key bypass when unconfigured

**File:** `apps/api/app/merchant_agreements/router.py`

The TiTo validation endpoint is completely open if `TITO_API_KEY` env var is not set. Fix the API key check to **reject all requests** when the key is not configured:

Find the API key check and change it to:

```python
if not settings.tito_api_key:
    raise HTTPException(status_code=503, detail="TiTo API key not configured")
if x_api_key != settings.tito_api_key:
    raise HTTPException(status_code=401, detail="Invalid API key")
```

## 5.2 Boldsign webhook signature verification

**File:** `apps/api/app/boldsign/router.py`

The webhook endpoint has no authentication. Add basic signature verification:

```python
import hmac
import hashlib

BOLDSIGN_WEBHOOK_SECRET = settings.boldsign_api_key  # Or a dedicated webhook secret

@router.post("/webhooks/boldsign")
async def boldsign_webhook(
    request: Request,
    supabase: Client = Depends(get_supabase),
):
    # Verify webhook signature if secret is configured
    if settings.boldsign_api_key:
        signature = request.headers.get("X-BoldSign-Signature", "")
        body_bytes = await request.body()
        expected = hmac.new(
            settings.boldsign_api_key.encode(),
            body_bytes,
            hashlib.sha256
        ).hexdigest()
        if not hmac.compare_digest(signature, expected):
            raise HTTPException(status_code=401, detail="Invalid webhook signature")
    # ... rest of handler
```

---

# SECTION 6: FIX PYDANTIC EXCLUDE_NONE vs EXCLUDE_UNSET (MEDIUM)

For PATCH/update operations, `exclude_none=True` prevents explicitly setting a field to `null`. Change to `exclude_unset=True` in these files:

## 6.1 `apps/api/app/obligations/service.py`

In `create_obligation()` and `update_obligation()`:
```python
# Change: body.model_dump(exclude_none=True)
# To:     body.model_dump(exclude_unset=True)
```

## 6.2 `apps/api/app/reminders/service.py`

In `create_reminder()` and `update_reminder()`:
```python
# Change: body.model_dump(exclude_none=True)
# To:     body.model_dump(exclude_unset=True)
```

## 6.3 `apps/api/app/escalation/service.py`

In `create_rule()` and `update_rule()`:
```python
# Change: body.model_dump(exclude_none=True)
# To:     body.model_dump(exclude_unset=True)
```

---

# SECTION 7: FIX JWKS CACHE EXPIRY (MEDIUM)

**File:** `apps/api/app/auth/jwt.py`

The `@lru_cache(maxsize=1)` on `_get_azure_jwks()` permanently caches keys. If Azure AD rotates signing keys, the app rejects all tokens until restarted.

**Fix:** Replace `@lru_cache` with a TTL cache:

```python
import time as _time

_jwks_cache: dict = {}
_jwks_cache_ttl = 3600  # 1 hour

def _get_azure_jwks(issuer: str) -> dict:
    """Fetch and cache JWKS from Azure AD with 1-hour TTL."""
    now = _time.monotonic()
    if issuer in _jwks_cache and now - _jwks_cache[issuer]["fetched_at"] < _jwks_cache_ttl:
        return _jwks_cache[issuer]["keys"]

    jwks_url = f"{issuer}/.well-known/openid-configuration"
    config = httpx.get(jwks_url).json()
    jwks = httpx.get(config["jwks_uri"]).json()
    _jwks_cache[issuer] = {"keys": jwks, "fetched_at": now}
    return jwks
```

Remove the `@lru_cache` decorator and the `from functools import lru_cache` import if it was only used here.

---

# SECTION 8: ADD MISSING RESPONSE = NONE FIX (LOW)

In several routers, `response: Response = None` is used. While FastAPI injects the Response, the `= None` default is misleading. Remove the default:

**Files to fix:**
- `apps/api/app/obligations/router.py` — change `response: Response = None` to `response: Response`
- `apps/api/app/escalation/router.py` — same
- `apps/api/app/notifications/router.py` — same

---

# SECTION 9: REMOVE UNUSED IMPORTS (LOW)

1. `apps/api/app/ai_analysis/schemas.py` — remove `from uuid import UUID`
2. `apps/api/app/ai/config.py` — remove `from app.config import settings`
3. `apps/api/app/contracts/schemas.py` — remove `from uuid import UUID`

---

# SECTION 10: FIX CONTRACT_LINKS KEYERROR (LOW)

**File:** `apps/api/app/contract_links/service.py`

In the `list_linked` function, change the dictionary population to handle unexpected link types:

```python
# Change:
result[link["link_type"]].append(child)
# To:
result.setdefault(link["link_type"], []).append(child)
```

---

# SECTION 11: FRONTEND FIXES (CRITICAL + HIGH)

## 11.1 Fix wiki-contracts detail page params (CRITICAL)

**File:** `apps/web/src/app/(dashboard)/wiki-contracts/[id]/page.tsx`

The `params` prop is typed as `{ id: string }` but Next.js 16 passes it as `Promise<{ id: string }>`. This crashes at runtime.

**Fix:** Restructure the page to use the standard server/client component pattern:

Remove `'use client'` from this page file. Make it an async server component that awaits params and passes the ID to a client component:

```typescript
import WikiContractDetail from './wiki-contract-detail';

export default async function WikiContractDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <WikiContractDetail id={id} />;
}
```

Then **move** the existing page logic into a new `'use client'` component at `apps/web/src/app/(dashboard)/wiki-contracts/[id]/wiki-contract-detail.tsx` that accepts `{ id: string }` as a prop and uses `id` directly (no params).

## 11.2 Fix login page Azure AD provider ID (HIGH)

**File:** `apps/web/src/app/login/page.tsx`

The Microsoft sign-in button calls `signIn('azure-ad', ...)` but the provider is registered as `microsoft-entra-id` in `auth.ts`.

**Fix:** Change `'azure-ad'` to `'microsoft-entra-id'`:

```typescript
signIn('microsoft-entra-id', { callbackUrl: '/' })
```

## 11.3 Fix fake PDF export in reports (HIGH)

**File:** `apps/web/src/app/(dashboard)/reports/page.tsx`

The "Export PDF" buttons write raw JSON with a `.pdf` extension. This creates corrupt files.

**Fix:** Either:
- **Option A (recommended):** Remove the PDF export buttons entirely and keep only CSV export (which works correctly). Replace the 4 "Export PDF" buttons with "Export CSV" buttons that use proper CSV generation.
- **Option B:** Change the button labels from "Export PDF" to "Export JSON" and the file extension from `.pdf` to `.json` and MIME type from `application/pdf` to `application/json`.

Implement Option A: remove all `downloadText(filename, JSON.stringify(data), 'application/pdf')` calls and their buttons. Keep only the working CSV exports.

## 11.4 Fix mixed camelCase/snake_case in API request bodies (HIGH)

Several forms send fields in different naming conventions. The FastAPI backend uses Pydantic's `alias` with `populate_by_name=True`, so it accepts BOTH camelCase and snake_case. However, be consistent.

**Standardize on camelCase** for all frontend-to-API request bodies (matching the Pydantic aliases):

### `apps/web/src/app/(dashboard)/counterparties/counterparty-detail-page.tsx`
Change the edit form submission from:
```typescript
JSON.stringify({ legal_name, registration_number, address, jurisdiction, preferred_language })
```
To:
```typescript
JSON.stringify({ legalName: legal_name, registrationNumber: registration_number, address, jurisdiction, preferredLanguage: preferred_language })
```

Check ALL other form submissions across the app and ensure they use camelCase field names. Files to check:
- `create-counterparty-form.tsx` (already uses camelCase — OK)
- `create-entity-form.tsx` (uses camelCase regionId — OK)
- `create-project-form.tsx` (check)
- `upload-contract-form.tsx` (check)
- `contracts/contract-detail.tsx` (check all fetch calls)

## 11.5 Add missing .catch() and error handling to fetches (MEDIUM)

Add `.catch(e => { setError(e.message); })` to all fetch calls that are missing error handling:

**Files:**
- `apps/web/src/app/(dashboard)/entities/create-entity-form.tsx` — region fetch
- `apps/web/src/app/(dashboard)/projects/create-project-form.tsx` — entity fetch
- `apps/web/src/app/(dashboard)/contracts/upload-contract-form.tsx` — regions, entities, counterparties fetches
- `apps/web/src/app/(dashboard)/regions/edit-region-form.tsx` — region fetch
- `apps/web/src/app/(dashboard)/reports/page.tsx` — all 4 report fetches
- `apps/web/src/app/(dashboard)/escalations/page.tsx` — escalations fetch

Add a `.catch()` handler that either sets an error state or logs the error. Also add `if (!r.ok) throw new Error(r.status.toString())` before `.json()`.

## 11.6 Add loading states to pages missing them (MEDIUM)

**Files:**
- `apps/web/src/app/(dashboard)/reports/page.tsx` — add `loading` state, show spinner during initial data load
- `apps/web/src/app/(dashboard)/escalations/page.tsx` — add `loading` state
- `apps/web/src/app/(dashboard)/merchant-agreements/page.tsx` — add `loading` state for the 5 parallel fetches

## 11.7 Fix audit page client-side pagination (MEDIUM)

**File:** `apps/web/src/app/(dashboard)/audit/page.tsx`

Currently fetches 10,000 records into client memory. Switch to server-side pagination:

Change the fetch to use proper `limit` and `offset`:
```typescript
params.set('limit', '25');
params.set('offset', String(offset));
```

Add Previous/Next pagination buttons. Read the `X-Total-Count` header from the response for the total count display.

## 11.8 Fix contract-detail-page unknown type (MEDIUM)

**File:** `apps/web/src/app/(dashboard)/contracts/contract-detail-page.tsx`

Change `useState<unknown>(null)` to use the proper Contract type:
```typescript
import { Contract } from '@/lib/types';
const [contract, setContract] = useState<Contract | null>(null);
```

Remove the unsafe `as` casts.

## 11.9 Add proxy error handling for upstream failures (MEDIUM)

**File:** `apps/web/src/app/api/ccrs/[...path]/route.ts`

Wrap each `fetch()` call to the upstream API in a try/catch to handle network failures gracefully:

```typescript
try {
  const res = await fetch(`${API_BASE}/${path}`, { ... });
  return forwardResponse(res);
} catch (error) {
  return NextResponse.json(
    { error: 'API service unavailable' },
    { status: 502 }
  );
}
```

Apply to all 4 HTTP method handlers (GET, POST, PATCH, DELETE).

---

# SECTION 12: DATABASE MIGRATION FIXES

Create a new migration `supabase/migrations/20260218000000_rectification.sql`:

```sql
-- =============================================================================
-- Rectification: Missing bucket, indexes, constraints, audit protection
-- =============================================================================

-- 12.1 Create missing wiki-contracts storage bucket
INSERT INTO storage.buckets (id, name, public)
VALUES ('wiki-contracts', 'wiki-contracts', false)
ON CONFLICT (id) DO NOTHING;

-- 12.2 Add missing CHECK constraint on contracts.contract_type
ALTER TABLE contracts ADD CONSTRAINT chk_contracts_contract_type
  CHECK (contract_type IN ('Commercial', 'Merchant'));

-- 12.3 Fix channel mismatch: add 'calendar' to notifications
-- (reminders allows 'calendar' but notifications does not)
ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_channel_check;
ALTER TABLE notifications ADD CONSTRAINT notifications_channel_check
  CHECK (channel IN ('email', 'teams', 'calendar'));

-- 12.4 Missing indexes for commonly queried columns
CREATE INDEX IF NOT EXISTS idx_counterparties_status ON counterparties(status);
CREATE INDEX IF NOT EXISTS idx_counterparties_reg_number ON counterparties(registration_number) WHERE registration_number IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_entities_region_id ON entities(region_id);
CREATE INDEX IF NOT EXISTS idx_projects_entity_id ON projects(entity_id);
CREATE INDEX IF NOT EXISTS idx_workflow_instances_template ON workflow_instances(template_id);
CREATE INDEX IF NOT EXISTS idx_escalation_events_composite ON escalation_events(workflow_instance_id, rule_id) WHERE resolved_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_obligations_type ON obligations_register(obligation_type);

-- 12.5 Protect audit_log from modification (compliance)
CREATE OR REPLACE FUNCTION prevent_audit_modification()
RETURNS TRIGGER AS $$
BEGIN
  RAISE EXCEPTION 'Audit log records cannot be modified or deleted';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS prevent_audit_update ON audit_log;
CREATE TRIGGER prevent_audit_update
  BEFORE UPDATE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();

DROP TRIGGER IF EXISTS prevent_audit_delete ON audit_log;
CREATE TRIGGER prevent_audit_delete
  BEFORE DELETE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();

-- 12.6 Prevent deletion of contracts in executed or archived state
CREATE OR REPLACE FUNCTION prevent_final_contract_delete()
RETURNS TRIGGER AS $$
BEGIN
  IF OLD.workflow_state IN ('executed', 'archived') THEN
    RAISE EXCEPTION 'Cannot delete contracts in executed or archived state';
  END IF;
  RETURN OLD;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS prevent_contract_delete ON contracts;
CREATE TRIGGER prevent_contract_delete
  BEFORE DELETE ON contracts
  FOR EACH ROW EXECUTE FUNCTION prevent_final_contract_delete();
```

---

# SECTION 13: FIX COUNTERPARTY DELETE FK VIOLATION (MEDIUM)

**File:** `apps/api/app/counterparties/service.py`

The `delete()` function does not check for contract references before deleting. Contracts reference counterparties with `ON DELETE RESTRICT`, so the delete will fail with a database error.

Add a pre-check:

```python
async def delete(supabase: Client, counterparty_id: UUID, actor: CurrentUser) -> bool:
    # Check for existing contract references
    contracts = supabase.table("contracts").select("id").eq("counterparty_id", str(counterparty_id)).limit(1).execute()
    if contracts.data:
        raise ValueError("Cannot delete counterparty: it has associated contracts")

    result = supabase.table("counterparties").delete().eq("id", str(counterparty_id)).execute()
    # ... rest of function
```

And in the router, catch `ValueError` and return 400.

---

# SECTION 14: CREDENTIALS PROVIDER ENV CHECK (MEDIUM)

**File:** `apps/web/src/auth.ts`

The Credentials provider accepts any email with no password check and returns a hardcoded user ID `'dev-1'`. Add an environment check to disable it in production:

```typescript
const providers = [
  MicrosoftEntraID({
    clientId: process.env.AZURE_AD_CLIENT_ID ?? '',
    clientSecret: process.env.AZURE_AD_CLIENT_SECRET ?? '',
    issuer: process.env.AZURE_AD_ISSUER,
  }),
];

// Only add credentials provider in development
if (process.env.NODE_ENV === 'development') {
  providers.push(
    CredentialsProvider({
      name: 'Credentials',
      credentials: { email: { label: 'Email', type: 'email' } },
      async authorize(credentials) {
        if (!credentials?.email) return null;
        return {
          id: `dev-${(credentials.email as string).split('@')[0]}`,
          email: credentials.email as string,
          name: credentials.email as string,
        };
      },
    })
  );
}
```

Note: use a unique ID per email instead of hardcoded `'dev-1'` so audit trails differentiate dev users.

---

# SECTION 15: ADD NEXTAUTH TYPE AUGMENTATION (LOW)

**Create** `apps/web/src/types/next-auth.d.ts`:

```typescript
import { DefaultSession, DefaultJWT } from 'next-auth';

declare module 'next-auth' {
  interface Session {
    user: {
      id: string;
      roles?: string[];
    } & DefaultSession['user'];
    accessToken?: string;
  }
}

declare module 'next-auth/jwt' {
  interface JWT extends DefaultJWT {
    id?: string;
    roles?: string[];
    accessToken?: string;
  }
}
```

Then update `auth.ts` to remove the unsafe type casts in the JWT and session callbacks, using the properly augmented types instead.

---

# Completion Checklist

After all changes:
1. [ ] `cd apps/api && python -m py_compile app/main.py` — no import/syntax errors
2. [ ] `cd apps/api && pytest tests/ -v` — all tests pass
3. [ ] `cd apps/web && npm run build` — no errors
4. [ ] No Python file exceeds its expected de-duplicated line count
5. [ ] Phase 1c SQL migration is ~184 lines (not ~368)
6. [ ] New rectification migration applied to Supabase
7. [ ] `GET /docs` (Swagger) loads without errors — no duplicate routes
8. [ ] Wiki-contracts upload works (bucket exists)
9. [ ] Login page Microsoft button uses correct provider ID
10. [ ] Reports page has no "Export PDF" buttons (or they export valid files)
11. [ ] Audit page uses server-side pagination (not 10,000 rows client-side)
