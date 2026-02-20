# Cursor Prompt — CCRS Functional Gaps & Quality Hardening

**Copy everything below this line into Cursor as the prompt.**

---

## Context

The CCRS codebase has all 17 epics scaffolded across Phase 1a, 1b, and 1c. A separate rectification prompt (already delivered) addresses 67 bugs. **This prompt covers 7 functional gaps not addressed by any previous prompt**, plus quality hardening (tests, CI, response models).

**Pre-requisite:** The Code Rectification prompt (`Cursor-Prompt-Code-Rectification.md`) should be applied FIRST. This prompt assumes duplicate file content is already removed and critical bugs are fixed.

After each section, verify:
- Backend: `cd apps/api && python -m py_compile app/main.py && pytest tests/ -v`
- Frontend: `cd apps/web && npm run build`

---

# SECTION 1: COUNTERPARTY STATUS CHANGE NOTIFICATIONS (Epic 17.2)

When a counterparty's status changes (Active → Suspended, Active → Blacklisted, etc.), all users associated with active contracts involving that counterparty must be notified.

## 1.1 Backend — Notification Helper

**Create** `apps/api/app/notifications/helpers.py`:

```python
"""Notification creation helpers — called by other modules to enqueue notifications."""

from uuid import UUID
from supabase import Client
import structlog

logger = structlog.get_logger()


async def create_notification(
    supabase: Client,
    *,
    recipient_email: str,
    recipient_user_id: str | None = None,
    channel: str = "email",
    subject: str,
    body: str,
    related_resource_type: str | None = None,
    related_resource_id: str | None = None,
) -> dict:
    """Insert a pending notification row. The scheduler picks it up and sends it."""
    row = {
        "recipient_email": recipient_email,
        "recipient_user_id": recipient_user_id,
        "channel": channel,
        "subject": subject,
        "body": body,
        "related_resource_type": related_resource_type,
        "related_resource_id": related_resource_id,
        "status": "pending",
    }
    result = supabase.table("notifications").insert(row).execute()
    return result.data[0] if result.data else row


async def notify_counterparty_status_change(
    supabase: Client,
    counterparty_id: UUID,
    counterparty_name: str,
    old_status: str,
    new_status: str,
    reason: str,
    changed_by: str,
) -> int:
    """Find all users with active contracts for this counterparty and notify them."""
    # Find contracts linked to this counterparty that are in active workflow states
    contracts = (
        supabase.table("contracts")
        .select("id, title, created_by")
        .eq("counterparty_id", str(counterparty_id))
        .not_.is_("workflow_state", "null")
        .execute()
    )

    if not contracts.data:
        return 0

    # Collect unique user emails from contract creators
    # Also check workflow instance actors for broader notification
    emails: set[str] = set()
    for c in contracts.data:
        if c.get("created_by"):
            emails.add(c["created_by"])

    # Also find users who have taken workflow actions on these contracts
    contract_ids = [str(c["id"]) for c in contracts.data]
    instances = (
        supabase.table("workflow_instances")
        .select("id")
        .in_("contract_id", contract_ids)
        .eq("state", "active")
        .execute()
    )
    if instances.data:
        instance_ids = [str(i["id"]) for i in instances.data]
        actions = (
            supabase.table("workflow_stage_actions")
            .select("actor_email")
            .in_("instance_id", instance_ids)
            .execute()
        )
        for a in actions.data or []:
            if a.get("actor_email"):
                emails.add(a["actor_email"])

    # Create a notification for each unique recipient
    count = 0
    subject = f"Counterparty status changed: {counterparty_name}"
    body = (
        f"The counterparty '{counterparty_name}' has been changed from "
        f"'{old_status}' to '{new_status}'.\n\n"
        f"Reason: {reason}\n"
        f"Changed by: {changed_by}\n\n"
        f"This may affect {len(contracts.data)} active contract(s) you are involved with."
    )

    for email in emails:
        await create_notification(
            supabase,
            recipient_email=email,
            channel="email",
            subject=subject,
            body=body,
            related_resource_type="counterparty",
            related_resource_id=str(counterparty_id),
        )
        count += 1

    logger.info(
        "counterparty_status_notifications_sent",
        counterparty_id=str(counterparty_id),
        recipient_count=count,
    )
    return count
```

## 1.2 Wire Into Counterparty Status Change

**File:** `apps/api/app/counterparties/service.py`

In the `change_status` function, after the status update succeeds, call the notification helper:

```python
from app.notifications.helpers import notify_counterparty_status_change

async def change_status(supabase, counterparty_id, body, actor):
    # ... existing code that updates the status ...

    # After successful update, notify affected users
    if result.data:
        row = result.data[0]
        await notify_counterparty_status_change(
            supabase,
            counterparty_id=counterparty_id,
            counterparty_name=row.get("legal_name", "Unknown"),
            old_status=current.get("status", "Unknown"),
            new_status=body.status,
            reason=body.reason,
            changed_by=actor.email,
        )

    # ... return as before
```

The variable `current` is the counterparty record fetched earlier in the function (before the update). The variable `result` is the update result. Adapt variable names to match the actual code.

---

# SECTION 2: COUNTERPARTY OVERRIDE REQUEST FLOW (Epic 17.2)

When a user tries to create a contract with a non-Active counterparty, the system currently blocks with an error. Add an override request flow that routes the request to Legal for approval.

## 2.1 Database Migration

**Create** `supabase/migrations/20260218000001_override_requests.sql`:

```sql
-- Override request table for blocked counterparty contract creation
CREATE TABLE IF NOT EXISTS override_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    counterparty_id UUID NOT NULL REFERENCES counterparties(id),
    contract_title TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    requested_by_email TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    decided_by TEXT,
    decided_by_email TEXT,
    decision_comment TEXT,
    decided_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_override_requests_status ON override_requests(status) WHERE status = 'pending';
CREATE INDEX idx_override_requests_counterparty ON override_requests(counterparty_id);
```

## 2.2 Backend — Override Request Module

**Create** `apps/api/app/override_requests/__init__.py` (empty).

**Create** `apps/api/app/override_requests/schemas.py`:

```python
from pydantic import BaseModel, Field


class CreateOverrideRequestInput(BaseModel):
    contract_title: str = Field(..., alias="contractTitle")
    reason: str

    model_config = {"populate_by_name": True}


class DecideOverrideRequestInput(BaseModel):
    decision: str = Field(..., pattern="^(approved|rejected)$")
    comment: str | None = None
```

**Create** `apps/api/app/override_requests/service.py`:

```python
from uuid import UUID
from supabase import Client

from app.auth.models import CurrentUser
from app.notifications.helpers import create_notification


async def create_request(
    supabase: Client,
    counterparty_id: UUID,
    body,
    actor: CurrentUser,
) -> dict:
    row = {
        "counterparty_id": str(counterparty_id),
        "contract_title": body.contract_title,
        "requested_by": actor.id,
        "requested_by_email": actor.email,
        "reason": body.reason,
        "status": "pending",
    }
    result = supabase.table("override_requests").insert(row).execute()
    request_row = result.data[0]

    # Notify Legal users (find users with Legal role — for now, use a
    # configurable email or query signing_authority for Legal role holders)
    await create_notification(
        supabase,
        recipient_email="legal@digittal.com",  # TODO: replace with dynamic Legal role lookup
        channel="email",
        subject=f"Override request: contract with blocked counterparty",
        body=(
            f"User {actor.email} has requested an override to create a contract "
            f"'{body.contract_title}' with a non-active counterparty.\n\n"
            f"Reason: {body.reason}\n\n"
            f"Please review this request in the CCRS system."
        ),
        related_resource_type="override_request",
        related_resource_id=str(request_row["id"]),
    )

    # Audit log
    supabase.table("audit_log").insert({
        "action": "override_request_created",
        "resource_type": "override_request",
        "resource_id": str(request_row["id"]),
        "actor_id": actor.id,
        "actor_email": actor.email,
        "details": {"counterparty_id": str(counterparty_id), "reason": body.reason},
    }).execute()

    return request_row


def list_pending(supabase: Client, limit: int = 25, offset: int = 0):
    query = (
        supabase.table("override_requests")
        .select("*, counterparties(legal_name, status)", count="exact")
        .eq("status", "pending")
        .order("created_at", desc=True)
        .range(offset, offset + limit - 1)
    )
    result = query.execute()
    return result.data, result.count or 0


async def decide(
    supabase: Client,
    request_id: UUID,
    body,
    actor: CurrentUser,
) -> dict | None:
    existing = supabase.table("override_requests").select("*").eq("id", str(request_id)).single().execute()
    if not existing.data or existing.data["status"] != "pending":
        return None

    result = (
        supabase.table("override_requests")
        .update({
            "status": body.decision,
            "decided_by": actor.id,
            "decided_by_email": actor.email,
            "decision_comment": body.comment,
            "decided_at": "now()",
        })
        .eq("id", str(request_id))
        .execute()
    )

    row = result.data[0] if result.data else None
    if row:
        # Notify the original requester
        await create_notification(
            supabase,
            recipient_email=existing.data["requested_by_email"],
            channel="email",
            subject=f"Override request {body.decision}",
            body=(
                f"Your override request for '{existing.data['contract_title']}' "
                f"has been {body.decision} by {actor.email}."
                + (f"\n\nComment: {body.comment}" if body.comment else "")
            ),
            related_resource_type="override_request",
            related_resource_id=str(request_id),
        )

        # Audit log
        supabase.table("audit_log").insert({
            "action": f"override_request_{body.decision}",
            "resource_type": "override_request",
            "resource_id": str(request_id),
            "actor_id": actor.id,
            "actor_email": actor.email,
            "details": {"decision": body.decision, "comment": body.comment},
        }).execute()

    return row
```

**Create** `apps/api/app/override_requests/router.py`:

```python
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.override_requests.schemas import CreateOverrideRequestInput, DecideOverrideRequestInput
from app.override_requests.service import create_request, decide, list_pending
from supabase import Client

router = APIRouter(tags=["override_requests"])


@router.post("/counterparties/{counterparty_id}/override-requests")
async def override_request_create(
    counterparty_id: UUID,
    body: CreateOverrideRequestInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await create_request(supabase, counterparty_id, body, user)


@router.get(
    "/override-requests",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def override_request_list(
    limit: int = 25,
    offset: int = 0,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = list_pending(supabase, limit, offset)
    from starlette.responses import Response
    # Set X-Total-Count header
    return data  # FastAPI will serialize; add header via middleware or Response


@router.patch(
    "/override-requests/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def override_request_decide(
    id: UUID,
    body: DecideOverrideRequestInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await decide(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Override request not found or already decided")
    return row
```

## 2.3 Register Router

**File:** `apps/api/app/main.py`

Add after the existing router imports:

```python
from app.override_requests.router import router as override_requests_router
```

And add after the existing `app.include_router(...)` calls:

```python
app.include_router(override_requests_router)
```

## 2.4 Frontend — Override Request UI

**File:** `apps/web/src/app/(dashboard)/counterparties/counterparty-detail-page.tsx`

When the status is Suspended or Blacklisted, add a section that shows:
- "This counterparty is [status]. New contracts cannot be created."
- A "Request Override" button that opens a dialog with:
  - Contract title (text input)
  - Reason (textarea)
  - Submit button

On submit, POST to `/api/ccrs/counterparties/{id}/override-requests` with `{ contractTitle, reason }`.

**Create** `apps/web/src/app/(dashboard)/override-requests/page.tsx`:

A page for Legal/Admin users listing pending override requests. Each row shows:
- Counterparty name, contract title, requester, reason, date
- "Approve" and "Reject" buttons (each opens a dialog for an optional comment)

On decide, PATCH to `/api/ccrs/override-requests/{id}` with `{ decision: "approved"|"rejected", comment }`.

Add to the nav in `apps/web/src/components/app-nav.tsx`:

```typescript
{ href: '/override-requests', label: 'Overrides' },
```

Place it after the Escalations entry.

---

# SECTION 3: COUNTERPARTY DUPLICATE MERGE/LINK (Epic 8.2)

The fuzzy duplicate detection endpoint exists but there is no merge or link capability.

## 3.1 Database Migration

Add to the same or a new migration (`20260218000001` if Section 2 is included there, otherwise create `20260218000002_counterparty_merge.sql`):

```sql
-- Track merged counterparties for redirect/traceability
CREATE TABLE IF NOT EXISTS counterparty_merges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_counterparty_id UUID NOT NULL,
    target_counterparty_id UUID NOT NULL REFERENCES counterparties(id),
    merged_by TEXT NOT NULL,
    merged_by_email TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_counterparty_merges_source ON counterparty_merges(source_counterparty_id);
```

## 3.2 Backend — Merge Service

**Add** to `apps/api/app/counterparties/service.py`:

```python
async def merge_counterparties(
    supabase: Client,
    source_id: UUID,
    target_id: UUID,
    actor: CurrentUser,
) -> dict:
    """
    Merge source counterparty into target:
    1. Reassign all contracts from source to target
    2. Reassign all contacts from source to target
    3. Record the merge
    4. Soft-delete (or mark inactive) the source
    """
    # Verify both exist
    source = supabase.table("counterparties").select("*").eq("id", str(source_id)).single().execute()
    target = supabase.table("counterparties").select("*").eq("id", str(target_id)).single().execute()
    if not source.data or not target.data:
        raise ValueError("Source or target counterparty not found")

    if str(source_id) == str(target_id):
        raise ValueError("Cannot merge a counterparty into itself")

    # Reassign contracts
    supabase.table("contracts").update(
        {"counterparty_id": str(target_id)}
    ).eq("counterparty_id", str(source_id)).execute()

    # Reassign contacts
    supabase.table("counterparty_contacts").update(
        {"counterparty_id": str(target_id)}
    ).eq("counterparty_id", str(source_id)).execute()

    # Record the merge
    supabase.table("counterparty_merges").insert({
        "source_counterparty_id": str(source_id),
        "target_counterparty_id": str(target_id),
        "merged_by": actor.id,
        "merged_by_email": actor.email,
    }).execute()

    # Delete the source counterparty (now has no contracts/contacts)
    supabase.table("counterparties").delete().eq("id", str(source_id)).execute()

    # Audit log
    supabase.table("audit_log").insert({
        "action": "counterparty_merged",
        "resource_type": "counterparty",
        "resource_id": str(target_id),
        "actor_id": actor.id,
        "actor_email": actor.email,
        "details": {
            "source_id": str(source_id),
            "source_name": source.data.get("legal_name"),
            "target_id": str(target_id),
            "target_name": target.data.get("legal_name"),
        },
    }).execute()

    return target.data
```

## 3.3 Backend — Router Endpoint

**Add** to `apps/api/app/counterparties/router.py`:

```python
@router.post(
    "/counterparties/{id}/merge",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def counterparty_merge(
    id: UUID,
    body: MergeCounterpartyInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await merge_counterparties(supabase, body.source_id, id, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
```

**Add** to `apps/api/app/counterparties/schemas.py`:

```python
class MergeCounterpartyInput(BaseModel):
    source_id: UUID = Field(..., alias="sourceId", description="The duplicate counterparty to merge INTO this one")

    model_config = {"populate_by_name": True}
```

## 3.4 Frontend — Merge UI

On the counterparty detail page, when duplicate detection shows results, add a "Merge into this counterparty" button next to each duplicate result. This sends `POST /api/ccrs/counterparties/{targetId}/merge` with `{ sourceId }`.

Also add merge capability in the create-counterparty flow: when duplicates are shown during creation, allow the user to select a duplicate and navigate to it instead of creating a new record.

---

# SECTION 4: VISUAL WORKFLOW TEMPLATE VERSION DIFF (Epic 5)

When viewing a workflow template that has been published multiple times (version > 1), show a side-by-side diff of the current version vs the previous version.

## 4.1 Backend — Version History Endpoint

**Add** to `apps/api/app/workflows/service.py`:

```python
def get_template_versions(supabase: Client, template_id: UUID) -> list[dict]:
    """
    Return the current template and infer version history from audit log.
    Since templates are edited in-place and version is bumped on publish,
    we reconstruct history from audit_log entries with action='workflow_template_published'.
    """
    # Get audit entries for this template's publish events
    audit = (
        supabase.table("audit_log")
        .select("*")
        .eq("resource_type", "workflow_template")
        .eq("resource_id", str(template_id))
        .eq("action", "workflow_template_published")
        .order("created_at", desc=True)
        .execute()
    )
    return audit.data or []
```

**Add** to `apps/api/app/workflows/router.py`:

```python
@router.get("/workflow-templates/{id}/versions")
async def workflow_template_versions(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return get_template_versions(supabase, id)
```

## 4.2 Improve Publish to Snapshot Stages

Currently, `publish_template` bumps the version but doesn't snapshot the stages. Modify it to store the stages JSON in the audit log details so we can reconstruct diffs:

**File:** `apps/api/app/workflows/service.py`, in `publish_template`:

When writing the audit log entry for the publish action, include `stages_json` in the `details` field:

```python
supabase.table("audit_log").insert({
    "action": "workflow_template_published",
    "resource_type": "workflow_template",
    "resource_id": str(template_id),
    "actor_id": actor.id,
    "actor_email": actor.email,
    "details": {
        "version": new_version,
        "stages": current_template.get("stages_json"),  # snapshot of stages at publish time
    },
}).execute()
```

## 4.3 Frontend — Diff View

On the workflow template detail/edit page, add a "Version History" tab or button that:

1. Fetches `GET /api/ccrs/workflow-templates/{id}/versions`
2. If there are 2+ versions, shows a side-by-side comparison:
   - Left: previous version's stages (from audit details)
   - Right: current version's stages
3. Highlight differences:
   - Added stages in green
   - Removed stages in red
   - Modified stages in yellow (compare stage name, type, owners, approvers, transitions)

Implement this as a simple JSON diff — compare each stage by name and show field-level changes. Use a `<pre>` block with color coding or a table layout. No need for a full rich-diff library.

---

# SECTION 5: NOTIFICATION SCHEMAS AND EXPANDED ROUTER (Foundation)

The notification module has only 1 endpoint and no schemas. Expand it to support full notification management.

## 5.1 Create Notification Schemas

**Create** `apps/api/app/notifications/schemas.py`:

```python
from pydantic import BaseModel, Field


class CreateNotificationInput(BaseModel):
    recipient_email: str = Field(..., alias="recipientEmail")
    channel: str = Field("email", pattern="^(email|teams|calendar)$")
    subject: str
    body: str
    related_resource_type: str | None = Field(None, alias="relatedResourceType")
    related_resource_id: str | None = Field(None, alias="relatedResourceId")

    model_config = {"populate_by_name": True}
```

## 5.2 Expand Notification Router

**File:** `apps/api/app/notifications/router.py`

Add these endpoints (keep the existing `GET /notifications`):

```python
@router.get("/notifications/unread-count")
async def notification_unread_count(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = (
        supabase.table("notifications")
        .select("id", count="exact")
        .eq("recipient_email", user.email)
        .eq("status", "sent")
        .is_("read_at", "null")
        .execute()
    )
    return {"count": result.count or 0}


@router.patch("/notifications/{id}/read")
async def notification_mark_read(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = (
        supabase.table("notifications")
        .update({"read_at": "now()"})
        .eq("id", str(id))
        .eq("recipient_email", user.email)
        .execute()
    )
    if not result.data:
        raise HTTPException(status_code=404, detail="Notification not found")
    return result.data[0]


@router.post("/notifications/mark-all-read")
async def notification_mark_all_read(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    supabase.table("notifications").update({"read_at": "now()"}).eq(
        "recipient_email", user.email
    ).is_("read_at", "null").execute()
    return {"ok": True}
```

## 5.3 Add `read_at` Column

Add to the rectification migration or create a new one:

```sql
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS read_at TIMESTAMPTZ;
```

---

# SECTION 6: PYDANTIC RESPONSE MODELS (Epic A8 — OpenAPI Docs)

All endpoints currently return raw dicts. Add response models for proper OpenAPI documentation on the most important endpoints. Do NOT change the actual return values — just add the `response_model` parameter to the router decorators.

## 6.1 Create `apps/api/app/schemas/responses.py`

```python
"""Shared Pydantic response models for OpenAPI documentation."""

from datetime import datetime
from uuid import UUID

from pydantic import BaseModel, Field


class RegionOut(BaseModel):
    id: UUID
    name: str
    description: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class EntityOut(BaseModel):
    id: UUID
    name: str
    region_id: UUID
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class ProjectOut(BaseModel):
    id: UUID
    name: str
    entity_id: UUID
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class CounterpartyOut(BaseModel):
    id: UUID
    legal_name: str
    registration_number: str | None = None
    address: str | None = None
    jurisdiction: str | None = None
    preferred_language: str | None = None
    status: str
    status_reason: str | None = None
    status_changed_at: datetime | None = None
    status_changed_by: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class ContractOut(BaseModel):
    id: UUID
    title: str
    contract_type: str
    region_id: UUID | None = None
    entity_id: UUID | None = None
    project_id: UUID | None = None
    counterparty_id: UUID | None = None
    workflow_state: str | None = None
    signing_status: str | None = None
    storage_path: str | None = None
    file_name: str | None = None
    created_by: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class AuditLogOut(BaseModel):
    id: UUID
    action: str
    resource_type: str
    resource_id: str | None = None
    actor_id: str | None = None
    actor_email: str | None = None
    details: dict | None = None
    ip_address: str | None = None
    created_at: datetime

    model_config = {"from_attributes": True}


class WorkflowTemplateOut(BaseModel):
    id: UUID
    name: str
    version: int
    contract_type: str
    region_id: UUID | None = None
    entity_id: UUID | None = None
    project_id: UUID | None = None
    stages_json: list | None = None
    status: str
    created_by: str | None = None
    published_at: datetime | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class NotificationOut(BaseModel):
    id: UUID
    recipient_email: str
    channel: str
    subject: str
    body: str
    status: str
    related_resource_type: str | None = None
    related_resource_id: str | None = None
    read_at: datetime | None = None
    sent_at: datetime | None = None
    created_at: datetime

    model_config = {"from_attributes": True}
```

## 6.2 Apply Response Models to Key Routers

Add `response_model` to the router decorators. Examples:

**`apps/api/app/regions/router.py`:**
```python
from app.schemas.responses import RegionOut

@router.post("/regions", response_model=RegionOut, ...)
@router.get("/regions/{id}", response_model=RegionOut, ...)
```

**`apps/api/app/counterparties/router.py`:**
```python
from app.schemas.responses import CounterpartyOut

@router.post("/counterparties", response_model=CounterpartyOut, ...)
@router.get("/counterparties/{id}", response_model=CounterpartyOut, ...)
```

Apply the same pattern to entities, projects, contracts, audit, workflows, and notifications routers. For list endpoints that return `list[T]`, use `response_model=list[RegionOut]` etc.

**Important:** Only add `response_model` — do NOT change the return statements. FastAPI will serialize the dicts through the model automatically.

Create `apps/api/app/schemas/__init__.py` (empty) to make it a package.

---

# SECTION 7: CI PIPELINE FIX (A10)

**File:** `.github/workflows/ci.yml`

Replace the entire file with:

```yaml
name: CI

on:
  push:
    branches: [main, master, develop]
  pull_request:
    branches: [main, master, develop]

defaults:
  run:
    shell: bash

jobs:
  frontend:
    name: Frontend
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: apps/web
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: "npm"
          cache-dependency-path: apps/web/package-lock.json

      - run: npm ci
      - run: npm run lint --if-present
      - run: npm run build
        env:
          SKIP_ENV_VALIDATION: "1"

  backend:
    name: Backend
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: apps/api
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-python@v5
        with:
          python-version: "3.12"
          cache: "pip"
          cache-dependency-path: apps/api/requirements.txt

      - run: pip install -r requirements.txt

      - name: Lint (ruff)
        run: |
          pip install ruff
          ruff check app/ tests/

      - name: Type check (basic)
        run: python -m py_compile app/main.py

      - name: Test with coverage
        run: |
          pip install pytest-cov
          pytest tests/ -v --cov=app --cov-report=term-missing --cov-fail-under=60
        env:
          SUPABASE_URL: https://test.supabase.co
          SUPABASE_SERVICE_ROLE_KEY: test-key
          JWT_SECRET: test-secret
```

Key changes:
- Removed the useless root `lint-and-test` job
- Added `ruff` linting for Python
- Added `py_compile` check for import errors
- Added `pytest-cov` with 60% minimum threshold (increase to 80% as tests are added)
- Removed unnecessary `--if-present` flags (fail fast if scripts are missing)
- Removed `has-frontend`/`has-backend` existence checks (the files always exist)

---

# SECTION 8: TEST SUITE EXPANSION (A9)

The test infrastructure (conftest.py with MockSupabase) is solid. Add tests for the uncovered areas. Create these test files:

## 8.1 `apps/api/tests/test_middleware.py`

```python
import pytest


@pytest.mark.asyncio
async def test_request_logging_middleware(authed_client):
    """Verify the logging middleware does not break requests."""
    response = authed_client.get("/health")
    assert response.status_code == 200


@pytest.mark.asyncio
async def test_error_handler_not_found(authed_client):
    """Verify 404 for non-existent routes."""
    response = authed_client.get("/nonexistent-route-xyz")
    assert response.status_code in (404, 405)


@pytest.mark.asyncio
async def test_error_handler_returns_json(authed_client):
    """Verify error responses are JSON."""
    response = authed_client.get("/regions/00000000-0000-0000-0000-000000000000")
    # Even if 404, should be JSON
    assert response.headers.get("content-type", "").startswith("application/json")
```

## 8.2 `apps/api/tests/test_override_requests.py`

```python
import pytest


def test_create_override_request(authed_client, mock_supabase):
    """Test creating an override request for a blocked counterparty."""
    # Get the blacklisted counterparty ID from seed data
    cps = mock_supabase.table("counterparties").select("*").eq("status", "Blacklisted").execute()
    if not cps.data:
        pytest.skip("No blacklisted counterparty in seed data")
    cp_id = cps.data[0]["id"]

    response = authed_client.post(
        f"/counterparties/{cp_id}/override-requests",
        json={"contractTitle": "Test Contract", "reason": "Urgent business need"},
    )
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "pending"


def test_list_pending_override_requests(authed_client):
    """Test listing pending override requests (requires Legal/Admin)."""
    response = authed_client.get("/override-requests")
    assert response.status_code == 200


def test_decide_override_request_requires_legal(client_context):
    """Test that only Legal/Admin can decide override requests."""
    with client_context(roles=["Commercial"]) as client:
        response = client.patch(
            "/override-requests/00000000-0000-0000-0000-000000000099",
            json={"decision": "approved"},
        )
        assert response.status_code == 403
```

## 8.3 `apps/api/tests/test_counterparty_merge.py`

```python
def test_merge_counterparties(authed_client, mock_supabase):
    """Test merging a duplicate counterparty into the target."""
    # Create two counterparties
    cp1 = authed_client.post(
        "/counterparties",
        json={"legalName": "Duplicate Corp"},
    ).json()
    cp2 = authed_client.post(
        "/counterparties",
        json={"legalName": "Original Corp"},
    ).json()

    response = authed_client.post(
        f"/counterparties/{cp2['id']}/merge",
        json={"sourceId": cp1["id"]},
    )
    assert response.status_code == 200


def test_merge_into_self_fails(authed_client, mock_supabase):
    """Test that merging a counterparty into itself fails."""
    cps = authed_client.get("/counterparties").json()
    if not cps:
        return
    cp_id = cps[0]["id"]

    response = authed_client.post(
        f"/counterparties/{cp_id}/merge",
        json={"sourceId": cp_id},
    )
    assert response.status_code == 400


def test_merge_requires_legal_or_admin(client_context):
    """Test that merge requires Legal or System Admin role."""
    with client_context(roles=["Viewer"]) as client:
        response = client.post(
            "/counterparties/00000000-0000-0000-0000-000000000001/merge",
            json={"sourceId": "00000000-0000-0000-0000-000000000002"},
        )
        assert response.status_code == 403
```

## 8.4 `apps/api/tests/test_state_machine_extended.py`

```python
from app.workflows.state_machine import can_actor_act, get_next_stage, validate_template
from app.workflows.schemas import WorkflowStage


def _stage(name, type_="approval", **kwargs):
    return WorkflowStage(name=name, type=type_, **kwargs)


def test_validate_empty_stages():
    errors = validate_template([])
    assert any("at least" in e.lower() for e in errors)


def test_validate_duplicate_stage_names():
    stages = [
        _stage("Review", allowed_transitions=["Review"]),
        _stage("Review", allowed_transitions=[]),
    ]
    errors = validate_template(stages)
    assert any("unique" in e.lower() or "duplicate" in e.lower() for e in errors)


def test_validate_first_stage_is_signing():
    stages = [
        _stage("Sign First", type_="signing", allowed_transitions=["Approve"]),
        _stage("Approve", allowed_transitions=[]),
    ]
    errors = validate_template(stages)
    assert any("signing" in e.lower() and "first" in e.lower() for e in errors)


def test_validate_orphan_stage():
    stages = [
        _stage("Review", allowed_transitions=["Approval"]),
        _stage("Approval", type_="approval", allowed_transitions=["Sign"]),
        _stage("Sign", type_="signing", allowed_transitions=[]),
        _stage("Orphan", allowed_transitions=[]),
    ]
    errors = validate_template(stages)
    assert any("orphan" in e.lower() or "unreachable" in e.lower() for e in errors)


def test_get_next_stage_reject():
    stages = [
        _stage("Draft", type_="draft", allowed_transitions=["Review"]),
        _stage("Review", allowed_transitions=["Sign"]),
        _stage("Sign", type_="signing", allowed_transitions=[]),
    ]
    result = get_next_stage(stages, "Review", "reject")
    assert result == "Draft"


def test_get_next_stage_reject_at_first_stage():
    stages = [
        _stage("Review", allowed_transitions=["Sign"]),
        _stage("Sign", type_="signing", allowed_transitions=[]),
    ]
    result = get_next_stage(stages, "Review", "reject")
    assert result == "Review"  # stays at current


def test_get_next_stage_approve_terminal():
    stages = [
        _stage("Review", allowed_transitions=[]),
    ]
    result = get_next_stage(stages, "Review", "approve")
    assert result is None  # completed


def test_get_next_stage_unknown_stage():
    stages = [_stage("Review", allowed_transitions=[])]
    result = get_next_stage(stages, "NonExistent", "approve")
    assert result is None


def test_can_actor_act_by_user_id():
    stage = _stage("Review", owners=["user-123"])

    class Actor:
        id = "user-123"
        email = "test@test.com"
        roles = []

    assert can_actor_act(stage, Actor(), signing_authority=None)


def test_can_actor_act_denied():
    stage = _stage("Review", owners=["user-other"], approvers=["Legal"])

    class Actor:
        id = "user-123"
        email = "test@test.com"
        roles = ["Viewer"]

    assert not can_actor_act(stage, Actor(), signing_authority=None)
```

## 8.5 `apps/api/tests/test_notifications_expanded.py`

```python
def test_notification_unread_count(authed_client):
    response = authed_client.get("/notifications/unread-count")
    assert response.status_code == 200
    assert "count" in response.json()


def test_notification_mark_all_read(authed_client):
    response = authed_client.post("/notifications/mark-all-read")
    assert response.status_code == 200


def test_notifications_list_unauthenticated(unauthed_client):
    response = unauthed_client.get("/notifications")
    assert response.status_code == 401
```

## 8.6 Update conftest.py for new tables

**File:** `apps/api/tests/conftest.py`

Add seed data for the `override_requests` and `counterparty_merges` tables in the `MockSupabase.__init__` data dictionary:

```python
"override_requests": [],
"counterparty_merges": [],
```

---

# Completion Checklist

After all changes:
1. [ ] `cd apps/api && python -m py_compile app/main.py` — no import/syntax errors
2. [ ] `cd apps/api && pytest tests/ -v` — all tests pass
3. [ ] `cd apps/web && npm run build` — no errors
4. [ ] New `override_requests` table migration applied
5. [ ] `counterparty_merges` table migration applied
6. [ ] `notifications` table has `read_at` column
7. [ ] Override requests page accessible from nav
8. [ ] Counterparty status change triggers notifications
9. [ ] Merge button visible on counterparty detail when duplicates exist
10. [ ] CI pipeline runs ruff lint + pytest with coverage
11. [ ] `/docs` (Swagger) shows response models for key endpoints
12. [ ] Workflow template version history endpoint returns data
