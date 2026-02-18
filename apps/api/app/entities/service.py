from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.entities.schemas import CreateEntityInput, UpdateEntityInput


async def create(
    supabase: Client,
    body: CreateEntityInput,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("entities")
        .insert({
            "region_id": str(body.region_id),
            "name": body.name,
            "code": body.code,
        })
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="entity.create",
        resource_type="entity",
        resource_id=data.get("id"),
        details={"name": body.name},
        actor=actor,
    )
    return data


def list_all(
    supabase: Client,
    region_id: UUID | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("entities")
        .select("*, regions(id, name, code)", count="exact")
        .order("name")
        .range(offset, offset + limit - 1)
    )
    if region_id is not None:
        q = q.eq("region_id", str(region_id))
    r = q.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = (
        supabase.table("entities")
        .select("*, regions(id, name, code)")
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    return r.data[0]


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateEntityInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True, by_alias=False)
    if "region_id" in payload and payload["region_id"] is not None:
        payload["region_id"] = str(payload["region_id"])
    if not payload:
        return get_by_id(supabase, id)
    r = (
        supabase.table("entities")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="entity.update",
        resource_type="entity",
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
    r = supabase.table("entities").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="entity.delete",
            resource_type="entity",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
