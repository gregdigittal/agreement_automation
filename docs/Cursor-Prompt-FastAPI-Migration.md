# Cursor Prompt — Migrate CCRS API from NestJS to Python FastAPI

**Copy everything below this line into Cursor as the prompt.**

---

## Context

You are working on the CCRS (Contract & Merchant Agreement Repository System) project. The current codebase has:

- **`apps/api`** — A NestJS backend (TypeScript) that needs to be **replaced** with Python FastAPI
- **`apps/web`** — Next.js 16 frontend (React 19, TypeScript, shadcn/ui, NextAuth v5) — **do not modify**
- **`supabase/migrations/`** — PostgreSQL schema — a new migration will be added

A code audit identified **30 bugs**, **18 missing features**, and **28 architectural gaps** in the NestJS API. Instead of patching it, we are migrating to **Python (FastAPI)** per the CCRS Requirements v3 Board Edition 4 specification.

**The full audit report is at:** `docs/Phase1a-Audit-and-Remediation.md`

**The requirements and build plan are at:** `docs/CCRS-Backlog-and-Build-Plan.md`

**The database schema is at:** `supabase/migrations/20260216000000_phase1a_schema.sql`

## Goal

Replace `apps/api` (NestJS) with `apps/api` (Python FastAPI) that:

1. Is a **complete, working replacement** — the Next.js frontend proxy at `apps/web/src/app/api/ccrs/[...path]/route.ts` must work without changes (same URL paths, same JSON shapes, same auth flow)
2. Fixes **all bugs** found in the audit
3. Implements **all missing Phase 1a features** (Epics 1, 2, 7, 8, 14, 17)
4. Follows the technology stack from Board Edition 4: FastAPI, Pydantic, async, structured logging
5. Is ready for Phase 1c AI integration (Claude Agent SDK, MCP tools) — structure the project so AI modules can be added later

## Critical Constraint: API Contract Compatibility

The Next.js frontend proxies ALL API calls through `apps/web/src/app/api/ccrs/[...path]/route.ts`. This proxy:

- Extracts a JWT (NextAuth session token signed with `AUTH_SECRET`) and sends it as `Authorization: Bearer <token>`
- Forwards to `NEXT_PUBLIC_API_URL` (default `http://localhost:4000`)
- Passes query parameters, JSON bodies, and multipart form data

**The FastAPI backend MUST:**
- Listen on port 4000 (configurable via `PORT` env var)
- Validate JWT Bearer tokens (signed with `JWT_SECRET`, which equals the frontend's `AUTH_SECRET`)
- Support optional Azure AD / Entra ID token validation when `AZURE_AD_*` env vars are set
- Use the **exact same URL paths and response shapes** as the current NestJS API (listed below)
- Accept the same Content-Types (JSON + multipart for file upload)

---

## STEP 1: Project Scaffolding

Delete the contents of `apps/api/` (keep the directory) and create a new Python FastAPI project.

### Directory structure

```
apps/api/
├── .env.example
├── .gitignore
├── Dockerfile
├── requirements.txt
├── pyproject.toml
├── README.md
├── app/
│   ├── __init__.py
│   ├── main.py                    # FastAPI app creation, middleware, startup
│   ├── config.py                  # Settings from env vars (pydantic-settings)
│   ├── deps.py                    # Shared dependencies (Supabase client, current user)
│   ├── auth/
│   │   ├── __init__.py
│   │   ├── jwt.py                 # JWT decode & validation
│   │   ├── models.py              # CurrentUser, Role enum
│   │   └── dependencies.py        # get_current_user, require_roles
│   ├── audit/
│   │   ├── __init__.py
│   │   ├── router.py              # GET /audit/resource/... and GET /audit/export
│   │   ├── service.py             # log(), find_for_resource(), export()
│   │   └── schemas.py             # AuditEntry, AuditExportFilters
│   ├── regions/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── entities/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── projects/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── counterparties/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── counterparty_contacts/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── contracts/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── signing_authority/
│   │   ├── __init__.py
│   │   ├── router.py
│   │   ├── service.py
│   │   └── schemas.py
│   ├── health/
│   │   ├── __init__.py
│   │   └── router.py
│   └── middleware/
│       ├── __init__.py
│       ├── logging.py             # Structured request logging
│       └── error_handler.py       # Global exception handler
├── tests/
│   ├── __init__.py
│   ├── conftest.py                # Fixtures: mock Supabase client, test user
│   ├── test_regions.py
│   ├── test_entities.py
│   ├── test_projects.py
│   ├── test_counterparties.py
│   ├── test_contracts.py
│   ├── test_signing_authority.py
│   ├── test_counterparty_contacts.py
│   └── test_audit.py
```

### requirements.txt

```
fastapi>=0.115.0
uvicorn[standard]>=0.34.0
pydantic>=2.10.0
pydantic-settings>=2.7.0
python-jose[cryptography]>=3.3.0
python-multipart>=0.0.18
supabase>=2.12.0
httpx>=0.28.0
structlog>=24.4.0
pytest>=8.3.0
pytest-asyncio>=0.25.0
pytest-httpx>=0.34.0
```

### pyproject.toml

```toml
[project]
name = "ccrs-api"
version = "0.1.0"
description = "CCRS API — Contract & Merchant Agreement Repository System"
requires-python = ">=3.12"

[tool.pytest.ini_options]
asyncio_mode = "auto"
testpaths = ["tests"]
```

### .env.example

```env
# Supabase (required)
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key

# JWT validation (use same secret as Next.js AUTH_SECRET)
JWT_SECRET=your-shared-secret-with-nextjs

# Optional: Azure AD / Entra ID
# AZURE_AD_CLIENT_ID=
# AZURE_AD_CLIENT_SECRET=
# AZURE_AD_ISSUER=https://login.microsoftonline.com/{tenant}/v2.0

# Server
PORT=4000
CORS_ORIGIN=http://localhost:3000
LOG_LEVEL=info
```

### .gitignore

```
__pycache__/
*.pyc
.env
.venv/
venv/
*.egg-info/
dist/
.pytest_cache/
.mypy_cache/
```

### Dockerfile

```dockerfile
FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
EXPOSE 4000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "4000"]
```

---

## STEP 2: Core Infrastructure

### app/config.py

Use `pydantic-settings` to load all configuration from environment variables. **Do NOT provide fallback secrets** — the app must fail to start if `JWT_SECRET` and `SUPABASE_URL` are not set.

```python
from pydantic_settings import BaseSettings
from pydantic import Field

class Settings(BaseSettings):
    # Required
    supabase_url: str
    supabase_service_role_key: str
    jwt_secret: str

    # Optional Azure AD
    azure_ad_client_id: str | None = None
    azure_ad_client_secret: str | None = None
    azure_ad_issuer: str | None = None

    # Server
    port: int = 4000
    cors_origin: str = "http://localhost:3000"
    log_level: str = "info"

    model_config = {"env_file": ".env", "env_file_encoding": "utf-8"}

settings = Settings()
```

### app/deps.py

Create a singleton Supabase client and shared dependencies:

```python
from supabase import create_client, Client
from app.config import settings

_supabase_client: Client | None = None

def get_supabase() -> Client:
    global _supabase_client
    if _supabase_client is None:
        _supabase_client = create_client(settings.supabase_url, settings.supabase_service_role_key)
    return _supabase_client
```

### app/main.py

```python
import structlog
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from contextlib import asynccontextmanager

from app.config import settings
from app.middleware.logging import RequestLoggingMiddleware
from app.middleware.error_handler import global_exception_handler
from app.health.router import router as health_router
from app.regions.router import router as regions_router
from app.entities.router import router as entities_router
from app.projects.router import router as projects_router
from app.counterparties.router import router as counterparties_router
from app.counterparty_contacts.router import router as contacts_router
from app.contracts.router import router as contracts_router
from app.signing_authority.router import router as signing_authority_router
from app.audit.router import router as audit_router

structlog.configure(
    processors=[
        structlog.contextvars.merge_contextvars,
        structlog.processors.add_log_level,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.dev.ConsoleRenderer() if settings.log_level == "debug" else structlog.processors.JSONRenderer(),
    ],
    wrapper_class=structlog.make_filtering_bound_logger(settings.log_level.upper()),
)

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger = structlog.get_logger()
    logger.info("ccrs_api_starting", port=settings.port)
    yield
    logger.info("ccrs_api_stopping")

app = FastAPI(
    title="CCRS API",
    version="0.1.0",
    lifespan=lifespan,
)

# CORS — trim whitespace from origins (fixes audit bug AC-16)
origins = [o.strip() for o in settings.cors_origin.split(",")]
app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Structured request logging
app.add_middleware(RequestLoggingMiddleware)

# Global exception handler
app.add_exception_handler(Exception, global_exception_handler)

# Root
@app.get("/")
async def root():
    return {"name": "CCRS API", "version": "0.1.0"}

# Mount all routers (NO prefix — paths must match NestJS exactly)
app.include_router(health_router)
app.include_router(regions_router)
app.include_router(entities_router)
app.include_router(projects_router)
app.include_router(counterparties_router)
app.include_router(contacts_router)
app.include_router(contracts_router)
app.include_router(signing_authority_router)
app.include_router(audit_router)
```

### app/middleware/logging.py

Structured request/response logging middleware using `structlog`. Log: method, path, status, duration_ms, user_id (if authenticated). Do NOT log request/response bodies.

### app/middleware/error_handler.py

Global exception handler that:
- Catches all unhandled exceptions
- Maps known Supabase/Postgres error patterns to HTTP status codes (duplicate → 409, FK violation → 400, not found → 404)
- Logs the full error with structlog
- Returns structured JSON: `{"detail": "Human-readable message"}`
- Never exposes raw database error messages in production

---

## STEP 3: Authentication & Authorization

### app/auth/jwt.py

Decode and validate JWT Bearer tokens. Must support **two modes**:

**Mode 1 — NextAuth session tokens (default):** The Next.js frontend signs session JWTs with `AUTH_SECRET` (which equals `JWT_SECRET`). Decode with `python-jose` using HS256. Extract: `sub` (user ID), `email`, and `iat`/`exp`.

**Mode 2 — Azure AD / Entra ID tokens (when `AZURE_AD_*` env vars set):** Validate RS256 tokens against Microsoft's JWKS endpoint. Extract: `oid` (user ID), `email` or `preferred_username`, `roles` claim (array of strings from Azure AD group assignments).

**Implementation:**

```python
from jose import jwt, JWTError, ExpiredSignatureError
from fastapi import HTTPException, status
from app.config import settings

def decode_token(token: str) -> dict:
    """Decode a JWT token. Tries HS256 (NextAuth) first, then RS256 (Azure AD) if configured."""
    # Try HS256 (NextAuth session token)
    try:
        payload = jwt.decode(
            token,
            settings.jwt_secret,
            algorithms=["HS256"],
            options={"verify_aud": False},
        )
        return payload
    except (JWTError, ExpiredSignatureError):
        pass

    # If Azure AD is configured, try RS256
    if settings.azure_ad_client_id and settings.azure_ad_issuer:
        try:
            # Fetch JWKS from Azure AD (cache this in production)
            payload = jwt.decode(
                token,
                settings.azure_ad_client_secret,  # Or fetch JWKS
                algorithms=["RS256"],
                audience=settings.azure_ad_client_id,
                issuer=settings.azure_ad_issuer,
            )
            return payload
        except (JWTError, ExpiredSignatureError):
            pass

    raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid or expired token")
```

### app/auth/models.py

```python
from pydantic import BaseModel
from enum import Enum

class Role(str, Enum):
    SYSTEM_ADMIN = "System Admin"
    LEGAL = "Legal"
    COMMERCIAL = "Commercial"
    FINANCE = "Finance"
    OPERATIONS = "Operations"
    AUDIT = "Audit"

class CurrentUser(BaseModel):
    id: str
    email: str | None = None
    roles: list[str] = []
    ip_address: str | None = None  # Captured from request for audit logging
```

### app/auth/dependencies.py

```python
from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from app.auth.jwt import decode_token
from app.auth.models import CurrentUser

security = HTTPBearer()

async def get_current_user(
    request: Request,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> CurrentUser:
    payload = decode_token(credentials.credentials)
    user_id = payload.get("oid") or payload.get("sub")
    if not user_id:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token: no subject")
    return CurrentUser(
        id=user_id,
        email=payload.get("email") or payload.get("preferred_username"),
        roles=payload.get("roles", []),
        ip_address=request.client.host if request.client else None,
    )

def require_roles(*allowed_roles: str):
    """Dependency factory that checks the user has at least one of the allowed roles."""
    async def check_roles(user: CurrentUser = Depends(get_current_user)) -> CurrentUser:
        if not any(role in user.roles for role in allowed_roles):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Requires one of: {', '.join(allowed_roles)}",
            )
        return user
    return check_roles
```

---

## STEP 4: Audit Service

### app/audit/service.py

The audit service is used by ALL domain modules. **Critical fixes from audit:**
- **Check the Supabase insert error** — log failures with structlog but do NOT raise exceptions (audit failures must not break mutations)
- **Always capture IP address** — passed from the `CurrentUser` dependency

```python
import structlog
from supabase import Client
from app.auth.models import CurrentUser

logger = structlog.get_logger()

async def audit_log(
    supabase: Client,
    *,
    action: str,
    resource_type: str,
    resource_id: str | None = None,
    details: dict | None = None,
    actor: CurrentUser | None = None,
) -> None:
    """Write an audit log entry. Never raises — logs errors instead."""
    try:
        result = supabase.table("audit_log").insert({
            "action": action,
            "resource_type": resource_type,
            "resource_id": resource_id,
            "details": details,
            "actor_id": actor.id if actor else None,
            "actor_email": actor.email if actor else None,
            "ip_address": actor.ip_address if actor else None,
        }).execute()
        if hasattr(result, "error") and result.error:
            logger.error("audit_log_insert_failed", action=action, error=str(result.error))
    except Exception as e:
        logger.error("audit_log_exception", action=action, error=str(e))
```

### app/audit/router.py

Endpoints:
- `GET /audit/resource/{resource_type}/{resource_id}` — Query params: `limit` (int, default 100, max 500)
- `GET /audit/export` — Query params: `from` (ISO datetime, optional), `to` (ISO datetime, optional), `resource_type` (optional), `actor_id` (optional), `limit` (int, default 10000, max 50000)

**Both endpoints require roles:** `System Admin`, `Legal`, or `Audit`.

**Validate date parameters** — use Pydantic `datetime` type in query models so invalid dates return 422, not a raw Postgres error.

### app/audit/schemas.py

```python
from pydantic import BaseModel, Field
from datetime import datetime

class AuditExportFilters(BaseModel):
    from_date: datetime | None = Field(None, alias="from")
    to_date: datetime | None = Field(None, alias="to")
    resource_type: str | None = None
    actor_id: str | None = None
    limit: int = Field(10000, ge=1, le=50000)

    model_config = {"populate_by_name": True}
```

---

## STEP 5: Domain Modules

Each domain module follows the same pattern: `router.py` (FastAPI router with endpoints), `service.py` (business logic using Supabase), `schemas.py` (Pydantic models for request/response validation).

### Naming Convention

Pydantic schemas use **camelCase** for field names (matching the current API's JSON contract) with `alias_generator` or `Field(alias=...)`, while Python code uses **snake_case** internally. Use `model_config = {"populate_by_name": True}` on all schemas.

### IMPORTANT: Response Shape Compatibility

The Next.js frontend expects the exact same JSON shapes the NestJS API returns. Supabase returns **snake_case** column names. The NestJS API returned Supabase data directly without transformation. **The FastAPI API must do the same** — return Supabase response data as-is (snake_case). Do NOT transform to camelCase in responses.

---

### 5.1 Regions Module

**Endpoints (exact paths — must match NestJS):**

| Method | Path | Auth | Roles | Description |
|--------|------|------|-------|-------------|
| `POST` | `/regions` | JWT | System Admin | Create region |
| `GET` | `/regions` | JWT | Any | List all regions (ordered by name) |
| `GET` | `/regions/{id}` | JWT | Any | Get region by ID |
| `PATCH` | `/regions/{id}` | JWT | System Admin | Update region |
| `DELETE` | `/regions/{id}` | JWT | System Admin | Delete region |

**Schemas:**
- `CreateRegionInput`: `name` (str, required, max 255), `code` (str | None, max 64)
- `UpdateRegionInput`: all fields optional

**Service:** Uses Supabase `.table("regions")`. All write operations call `audit_log()`. Handle FK constraint errors (entity references region) with a clear 409 error.

**Pagination:** Add `limit` (default 50, max 500) and `offset` (default 0) query params on the list endpoint. Return the array directly (not wrapped) for backward compatibility, but include `X-Total-Count` header.

---

### 5.2 Entities Module

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/entities` | JWT | System Admin |
| `GET` | `/entities` | JWT | Any |
| `GET` | `/entities/{id}` | JWT | Any |
| `PATCH` | `/entities/{id}` | JWT | System Admin |
| `DELETE` | `/entities/{id}` | JWT | System Admin |

**Query params on GET list:** `regionId` (optional UUID filter), `limit`, `offset`

**Join:** List returns entities with `regions(id,name,code)`. Detail returns the same.

**Schemas:** `CreateEntityInput`: `regionId` (UUID, required), `name` (str, required, max 255), `code` (str | None, max 64)

---

### 5.3 Projects Module

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/projects` | JWT | System Admin |
| `GET` | `/projects` | JWT | Any |
| `GET` | `/projects/{id}` | JWT | Any |
| `PATCH` | `/projects/{id}` | JWT | System Admin |
| `DELETE` | `/projects/{id}` | JWT | System Admin |

**Query params on GET list:** `entityId` (optional UUID filter), `limit`, `offset`

**Join:** List returns projects with `entities(id,name,code,region_id)`. Detail returns the same.

---

### 5.4 Counterparties Module

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `GET` | `/counterparties/duplicates` | JWT | Any |
| `POST` | `/counterparties` | JWT | System Admin, Legal, Commercial |
| `GET` | `/counterparties` | JWT | Any |
| `GET` | `/counterparties/{id}` | JWT | Any |
| `PATCH` | `/counterparties/{id}` | JWT | System Admin, Legal, Commercial |
| `PATCH` | `/counterparties/{id}/status` | JWT | Legal |
| `DELETE` | `/counterparties/{id}` | JWT | System Admin |

**CRITICAL FIX — Fuzzy duplicate detection (`GET /counterparties/duplicates`):**
- Query params: `legalName` (required), `registrationNumber` (optional)
- Use `ilike` WITH wildcards: `.ilike("legal_name", f"%{legal_name.strip()}%")` for substring matching
- Also match on exact `registration_number` if provided
- De-duplicate results by `id`
- Return: `[{id, legal_name, registration_number}]`

**CRITICAL FIX — Status change (`PATCH /counterparties/{id}/status`):**
- Schema: `status` (required, one of Active/Suspended/Blacklisted), `reason` (required, str), `supporting_document_ref` (optional, str)
- **Persist `supporting_document_ref`** to the database (the NestJS version silently dropped this field)
- Audit log the status change with `previous_status`, `new_status`, `reason`

**Detail (`GET /counterparties/{id}`):** Return with joined `counterparty_contacts(*)`.

**List (`GET /counterparties`):** Query param: `status` (optional filter), `limit`, `offset`.

---

### 5.5 Counterparty Contacts Module (NEW — was missing entirely)

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/counterparties/{counterparty_id}/contacts` | JWT | System Admin, Legal, Commercial |
| `GET` | `/counterparties/{counterparty_id}/contacts` | JWT | Any |
| `PATCH` | `/counterparty-contacts/{id}` | JWT | System Admin, Legal, Commercial |
| `DELETE` | `/counterparty-contacts/{id}` | JWT | System Admin, Legal |

**Schemas:**
- `CreateContactInput`: `name` (str, required), `email` (str | None), `role` (str | None), `is_signer` (bool, default false)
- `UpdateContactInput`: all fields optional

Audit all mutations.

---

### 5.6 Contracts Module

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/contracts/upload` | JWT | System Admin, Legal, Commercial |
| `GET` | `/contracts` | JWT | Any |
| `GET` | `/contracts/{id}` | JWT | Any |
| `GET` | `/contracts/{id}/download-url` | JWT | Any |
| `PATCH` | `/contracts/{id}` | JWT | System Admin, Legal | **NEW** |
| `DELETE` | `/contracts/{id}` | JWT | System Admin | **NEW** |

**CRITICAL FIX — Block non-active counterparties on upload:**

In the `POST /contracts/upload` handler, BEFORE uploading the file:
1. Query the `counterparties` table for the given `counterparty_id`
2. If `status` is not `'Active'`, return HTTP 400 with: `{"detail": "Cannot create contract: counterparty is {status}. Reason: {status_reason}"}`

**Upload (`POST /contracts/upload`):**
- Accept multipart form data: `file` (UploadFile), plus form fields: `regionId`, `entityId`, `projectId`, `counterpartyId`, `contractType`, `title`
- Validate file MIME type: only `application/pdf` and `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- Upload to Supabase Storage bucket `contracts` with path: `{region_id}/{entity_id}/{project_id}/{timestamp}-{filename}`
- Insert contract record in `contracts` table
- Audit log: `contract.upload`

**Search (`GET /contracts`):**
- Query params (all optional): `q` (full-text search), `regionId`, `entityId`, `projectId`, `contractType` (one of Commercial/Merchant), `workflowState`, `limit` (default 50, **max 500**), `offset` (default 0)
- Validate all UUID params as proper UUIDs using Pydantic
- Full-text search uses `.text_search("search_vector", query, config="english", type="websearch")`
- Return: array of contract objects with `id, title, contract_type, workflow_state, signing_status, created_at, region_id, entity_id, project_id, counterparty_id`

**Detail (`GET /contracts/{id}`):**
- Join: `regions(id,name), entities(id,name), projects(id,name), counterparties(id,legal_name,status)`

**Download URL (`GET /contracts/{id}/download-url`):**
- Generate 1-hour signed URL from Supabase Storage
- **Audit log this access:** `contract.download` (the NestJS version didn't audit downloads — this is a fix)

**Update (`PATCH /contracts/{id}`) — NEW:**
- Updateable fields: `title`, `contract_type`, `workflow_state`, `signing_status`
- Cannot update if `workflow_state` is `archived` or `executed` (immutability enforcement)
- Audit log: `contract.update`

**Delete (`DELETE /contracts/{id}`) — NEW:**
- Cannot delete if `workflow_state` is `executed` or `archived`
- Delete the file from Supabase Storage as well
- Audit log: `contract.delete`

---

### 5.7 Signing Authority Module (NEW — was missing entirely)

**Endpoints:**

| Method | Path | Auth | Roles |
|--------|------|------|-------|
| `POST` | `/signing-authority` | JWT | System Admin |
| `GET` | `/signing-authority` | JWT | Any |
| `GET` | `/signing-authority/{id}` | JWT | Any |
| `PATCH` | `/signing-authority/{id}` | JWT | System Admin |
| `DELETE` | `/signing-authority/{id}` | JWT | System Admin |

**Query params on list:** `entityId` (optional), `projectId` (optional), `limit`, `offset`

**Schemas:**
- `CreateSigningAuthorityInput`: `entity_id` (UUID, required), `project_id` (UUID | None), `user_id` (str, required), `user_email` (str | None), `role_or_name` (str, required), `contract_type_pattern` (str | None)
- `UpdateSigningAuthorityInput`: all fields optional

Audit all mutations.

---

### 5.8 Health Module

**Endpoint:** `GET /health` — **No authentication required**

- Probe the database with a neutral query (NOT a domain table): use `supabase.rpc("", {})` or query the Supabase health endpoint.
- Return: `{"status": "ok", "db": "connected"}` or `{"status": "degraded", "db": "error", "error": "..."}`

---

## STEP 6: Database Migration

Create `supabase/migrations/20260217000001_phase1a_fastapi_fixes.sql`:

```sql
-- Enable pg_trgm for fuzzy matching (Epic 8.2)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Add GIN trigram index for counterparty fuzzy search
CREATE INDEX IF NOT EXISTS idx_counterparties_legal_name_trgm
  ON counterparties USING GIN (legal_name gin_trgm_ops);

-- Add supporting_document_ref to counterparties (Epic 17.1 — was accepted but not persisted)
ALTER TABLE counterparties ADD COLUMN IF NOT EXISTS supporting_document_ref TEXT;

-- Add updated_at trigger for all tables (fixes manual updated_at in application code)
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
DECLARE
  t TEXT;
BEGIN
  FOR t IN SELECT unnest(ARRAY[
    'regions','entities','projects','counterparties',
    'contracts','counterparty_contacts','signing_authority'
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

## STEP 7: Tests

Write tests in the `tests/` directory using `pytest` and `pytest-asyncio`.

### tests/conftest.py

Create fixtures:
- `mock_supabase`: A mock Supabase client that returns predictable data for each table operation
- `test_user`: A `CurrentUser` instance with `id="test-1"`, `email="test@example.com"`, `roles=["System Admin"]`
- `client`: A `TestClient` or `httpx.AsyncClient` wrapping the FastAPI app with the Supabase dependency overridden

### Test coverage requirements (minimum)

For each domain module, test:

1. **Happy path CRUD** — create, list, get, update, delete all return expected status codes and shapes
2. **Validation** — missing required fields return 422; invalid UUIDs return 422
3. **Not found** — GET/PATCH/DELETE with a non-existent ID returns 404
4. **Auth** — requests without Bearer token return 401
5. **Roles** — requests with wrong role return 403 (for role-protected endpoints)

**Counterparty-specific tests:**
6. Fuzzy duplicate detection returns matches for substring and case variations
7. Status change persists `supporting_document_ref`
8. Contract creation is blocked when counterparty is Suspended or Blacklisted

**Contract-specific tests:**
9. Upload rejects non-PDF/DOCX files with 400
10. Upload rejects non-active counterparty with 400
11. Search with filters returns filtered results
12. Delete/update blocked for executed contracts

**Audit-specific tests:**
13. Audit log failures do not propagate as exceptions
14. Audit export validates date parameters

---

## STEP 8: Update Project Docs and Frontend Proxy

### Update docs/Phase-1a-Setup.md

Replace the API section with Python/FastAPI instructions:

```markdown
## 2. API (`apps/api`)

```bash
cd apps/api
python -m venv .venv
source .venv/bin/activate  # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
# Edit .env: set SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, JWT_SECRET
uvicorn app.main:app --reload --port 4000
```

- API runs at **http://localhost:4000**
- API docs at **http://localhost:4000/docs** (Swagger UI)
- Use the same `JWT_SECRET` as `AUTH_SECRET` on the web app.
```

### Update docs/Render-and-Vercel-Env-Setup.md

Update the Render section to reference Python deployment:
- Build command: `pip install -r requirements.txt`
- Start command: `uvicorn app.main:app --host 0.0.0.0 --port $PORT`
- Add `PYTHON_VERSION=3.12` env var

### Update apps/web .env.example

No changes needed — `NEXT_PUBLIC_API_URL` still points to the same backend URL.

### Update .github/workflows/ci.yml

Replace the backend job's Node.js steps with Python:

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
    - run: pip install -r requirements.txt
    - run: pytest tests/ -v
```

Remove `continue-on-error: true` from all steps except the root no-op job.

### Update README.md

Change the `apps/api` description from "NestJS backend" to "Python FastAPI backend".

---

## STEP 9: Verification Checklist

After all code is written, verify:

1. `cd apps/api && pip install -r requirements.txt` — installs cleanly
2. `cd apps/api && uvicorn app.main:app --port 4000` — starts without error (with valid `.env`)
3. `cd apps/api && pytest tests/ -v` — all tests pass
4. `cd apps/web && npm run build` — frontend builds (unchanged)
5. Open `http://localhost:4000/docs` — Swagger UI shows all endpoints
6. Open `http://localhost:4000/health` — returns `{"status": "ok", "db": "connected"}`
7. Start both API and web, sign in, and verify:
   - Regions CRUD works
   - Entities CRUD works (including edit page)
   - Projects CRUD works (including edit page)
   - Counterparties CRUD works (including status change)
   - Contract upload works (file appears in Supabase Storage)
   - Contract upload is BLOCKED for Suspended/Blacklisted counterparties
   - Counterparty duplicate detection finds substring matches
   - Audit export works

---

## Summary of What This Prompt Delivers vs. the NestJS Version

| Area | NestJS (had) | FastAPI (delivers) |
|------|-------------|-------------------|
| Fuzzy matching | Broken (exact match) | Substring + pg_trgm |
| Audit reliability | Silent failures | Errors logged, never thrown |
| Counterparty blocking | Not implemented | Enforced on contract creation |
| JWT security | Hardcoded fallback | Fails fast if missing |
| Supporting doc ref | Accepted, never saved | Persisted to DB |
| Error responses | Generic 500s | Proper 400/404/409/500 |
| Signing authority | Zero API code | Full CRUD module |
| Counterparty contacts | Zero API code | Full CRUD module |
| Contract update/delete | Not implemented | Full CRUD |
| Search validation | DTO unused | Pydantic-validated |
| Pagination | None | All list endpoints |
| RBAC | Audit only | All controllers |
| Tests | Zero | Full suite |
| Logging | console.log on startup | Structured JSON logging |
| API docs | None | Auto-generated Swagger/OpenAPI |
| IP in audit | Never captured | Always captured |
| updated_at | Manual in app code | Database trigger |
| Health check | Queries domain table | Neutral DB probe |
| CORS parsing | Whitespace bug | Trimmed |
