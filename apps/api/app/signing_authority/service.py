from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.signing_authority.schemas import CreateSigningAuthorityInput, UpdateSigningAuthorityInput


def _to_db(payload: dict) -> dict:
    out = {}
    for k, v in payload.items():
        if k in ("entity_id", "entityId"):
            out["entity_id"] = str(v) if v else None
        elif k in ("project_id", "projectId"):
            out["project_id"] = str(v) if v else None
        elif k in ("user_id", "userId"):
            out["user_id"] = v
        elif k in ("user_email", "userEmail"):
            out["user_email"] = v
        elif k in ("role_or_name", "roleOrName"):
            out["role_or_name"] = v
        elif k in ("contract_type_pattern", "contractTypePattern"):
            out["contract_type_pattern"] = v
        else:
            out[k] = v
    return out


async def create(
    supabase: Client,
    body: CreateSigningAuthorityInput,
    actor: CurrentUser | None,
) -> dict:
    payload = body.model_dump(by_alias=True)
    db = _to_db(payload)
    row = supabase.table("signing_authority").insert(db).execute()
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="signing_authority.create",
        resource_type="signing_authority",
        resource_id=data.get("id"),
        details={"user_id": body.user_id},
        actor=actor,
    )
    return data


def list_all(
    supabase: Client,
    entity_id: UUID | None = None,
    project_id: UUID | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("signing_authority")
        .select("*", count="exact")
        .order("created_at", desc=True)
        .range(offset, offset + limit - 1)
    )
    if entity_id is not None:
        q = q.eq("entity_id", str(entity_id))
    if project_id is not None:
        q = q.eq("project_id", str(project_id))
    r = q.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = supabase.table("signing_authority").select("*").eq("id", str(id)).execute()
    if not r.data:
        return None
    return r.data[0]


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateSigningAuthorityInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True, by_alias=True)
    if not payload:
        return get_by_id(supabase, id)
    db = _to_db(payload)
    r = supabase.table("signing_authority").update(db).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="signing_authority.update",
        resource_type="signing_authority",
        resource_id=str(id),
        details=db,
        actor=actor,
    )
    return r.data[0]


async def delete(
    supabase: Client,
    id: UUID,
    actor: CurrentUser | None,
) -> bool:
    r = supabase.table("signing_authority").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="signing_authority.delete",
            resource_type="signing_authority",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
