from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.regions.schemas import CreateRegionInput, UpdateRegionInput


async def create(
    supabase: Client,
    body: CreateRegionInput,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("regions")
        .insert({"name": body.name, "code": body.code})
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="region.create",
        resource_type="region",
        resource_id=data.get("id"),
        details={"name": body.name},
        actor=actor,
    )
    return data


def list_all(
    supabase: Client,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("regions")
        .select("*", count="exact")
        .order("name")
        .range(offset, offset + limit - 1)
    )
    r = q.execute()
    total = getattr(r, "count", None)
    if total is None and r.data is not None:
        total = len(r.data)
    return (r.data or [], total or 0)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = supabase.table("regions").select("*").eq("id", str(id)).execute()
    if not r.data:
        return None
    return r.data[0]


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateRegionInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True)
    if not payload:
        return get_by_id(supabase, id)
    r = (
        supabase.table("regions")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="region.update",
        resource_type="region",
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
    r = supabase.table("regions").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="region.delete",
            resource_type="region",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
