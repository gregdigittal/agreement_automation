from datetime import datetime, timezone
from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.wiki_contracts.schemas import CreateWikiContractInput, UpdateWikiContractInput

BUCKET = "wiki-contracts"
ALLOWED_MIME = {
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
}


async def create(supabase: Client, body: CreateWikiContractInput, actor: CurrentUser | None) -> dict:
    row = (
        supabase.table("wiki_contracts")
        .insert(
            {
                "name": body.name,
                "category": body.category,
                "region_id": str(body.region_id) if body.region_id else None,
                "description": body.description,
                "status": "draft",
                "version": 1,
                "created_by": actor.id if actor else None,
            }
        )
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="wiki_contract.create",
        resource_type="wiki_contract",
        resource_id=data.get("id"),
        details={"name": body.name},
        actor=actor,
    )
    return data


def list_all(
    supabase: Client,
    status: str | None = None,
    region_id: UUID | None = None,
    category: str | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("wiki_contracts")
        .select("*", count="exact")
        .order("created_at", desc=True)
        .range(offset, offset + limit - 1)
    )
    if status:
        q = q.eq("status", status)
    if region_id:
        q = q.eq("region_id", str(region_id))
    if category:
        q = q.eq("category", category)
    r = q.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = supabase.table("wiki_contracts").select("*").eq("id", str(id)).execute()
    if not r.data:
        return None
    return r.data[0]


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateWikiContractInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True, by_alias=False)
    if "region_id" in payload and payload["region_id"] is not None:
        payload["region_id"] = str(payload["region_id"])
    if not payload:
        return get_by_id(supabase, id)
    r = supabase.table("wiki_contracts").update(payload).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="wiki_contract.update",
        resource_type="wiki_contract",
        resource_id=str(id),
        details=payload,
        actor=actor,
    )
    return r.data[0]


async def publish(
    supabase: Client,
    id: UUID,
    actor: CurrentUser | None,
) -> dict | None:
    current = get_by_id(supabase, id)
    if not current:
        return None
    payload = {
        "status": "published",
        "published_at": datetime.now(timezone.utc).isoformat(),
        "version": int(current.get("version") or 1) + 1,
    }
    r = supabase.table("wiki_contracts").update(payload).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="wiki_contract.publish",
        resource_type="wiki_contract",
        resource_id=str(id),
        actor=actor,
    )
    return r.data[0]


async def delete(supabase: Client, id: UUID, actor: CurrentUser | None) -> bool:
    r = supabase.table("wiki_contracts").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="wiki_contract.delete",
            resource_type="wiki_contract",
            resource_id=str(id),
            actor=actor,
        )
    return deleted


async def upload_file(
    supabase: Client,
    *,
    id: UUID,
    file_name: str,
    content_type: str,
    file_bytes: bytes,
    actor: CurrentUser | None,
) -> dict | None:
    if content_type not in ALLOWED_MIME:
        raise ValueError("Only PDF and DOCX files are allowed")
    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    path = f"{id}/{timestamp}-{file_name}"
    supabase.storage.from_(BUCKET).upload(
        path=path,
        file=file_bytes,
        file_options={"content-type": content_type, "upsert": "false"},
    )
    r = (
        supabase.table("wiki_contracts")
        .update({"storage_path": path, "file_name": file_name})
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="wiki_contract.upload",
        resource_type="wiki_contract",
        resource_id=str(id),
        details={"storage_path": path},
        actor=actor,
    )
    return r.data[0]


def get_download_url(supabase: Client, path: str) -> str:
    result = supabase.storage.from_(BUCKET).create_signed_url(path, 3600)
    if isinstance(result, dict):
        return result.get("signedUrl") or result.get("signed_url") or result.get("path") or ""
    return getattr(result, "signed_url", None) or getattr(result, "signedUrl", None) or ""
