from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.projects.schemas import CreateProjectInput, UpdateProjectInput


async def create(
    supabase: Client,
    body: CreateProjectInput,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("projects")
        .insert({
            "entity_id": str(body.entity_id),
            "name": body.name,
            "code": body.code,
        })
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="project.create",
        resource_type="project",
        resource_id=data.get("id"),
        details={"name": body.name},
        actor=actor,
    )
    return data


def list_all(
    supabase: Client,
    entity_id: UUID | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("projects")
        .select("*, entities(id, name, code, region_id)", count="exact")
        .order("name")
        .range(offset, offset + limit - 1)
    )
    if entity_id is not None:
        q = q.eq("entity_id", str(entity_id))
    r = q.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = (
        supabase.table("projects")
        .select("*, entities(id, name, code, region_id)")
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    return r.data[0]


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateProjectInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True, by_alias=False)
    if "entity_id" in payload and payload["entity_id"] is not None:
        payload["entity_id"] = str(payload["entity_id"])
    if not payload:
        return get_by_id(supabase, id)
    r = (
        supabase.table("projects")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="project.update",
        resource_type="project",
        resource_id=str(id),
        details=payload,
        actor=actor,
    )
    return r.data[0]


async def delete(
    supabase: Client,
    id: UUID,
    actor: CurrentUser | None,
) -> bool:
    r = supabase.table("projects").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="project.delete",
            resource_type="project",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
