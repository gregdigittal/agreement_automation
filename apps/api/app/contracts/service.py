from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser

ALLOWED_MIME = {
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
}
BUCKET = "contracts"
IMMUTABLE_STATES = {"archived", "executed"}


def _check_counterparty_active(supabase: Client, counterparty_id: UUID) -> tuple[bool, str, str]:
    r = (
        supabase.table("counterparties")
        .select("status, status_reason")
        .eq("id", str(counterparty_id))
        .execute()
    )
    if not r.data:
        return False, "not_found", "Counterparty not found"
    row = r.data[0]
    status = (row.get("status") or "").strip()
    reason = (row.get("status_reason") or "") or ""
    if status != "Active":
        return False, status, reason
    return True, "", ""


def list_contracts(
    supabase: Client,
    q: str | None = None,
    region_id: UUID | None = None,
    entity_id: UUID | None = None,
    project_id: UUID | None = None,
    contract_type: str | None = None,
    workflow_state: str | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    sel = (
        supabase.table("contracts")
        .select(
            "id, title, contract_type, workflow_state, signing_status, created_at, region_id, entity_id, project_id, counterparty_id",
            count="exact",
        )
        .order("created_at", desc=True)
        .range(offset, offset + limit - 1)
    )
    if q and q.strip():
        sel = sel.text_search(
            "search_vector",
            q.strip(),
            options={"config": "english", "type": "websearch"},
        )
    if region_id is not None:
        sel = sel.eq("region_id", str(region_id))
    if entity_id is not None:
        sel = sel.eq("entity_id", str(entity_id))
    if project_id is not None:
        sel = sel.eq("project_id", str(project_id))
    if contract_type:
        sel = sel.eq("contract_type", contract_type)
    if workflow_state:
        sel = sel.eq("workflow_state", workflow_state)
    r = sel.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = (
        supabase.table("contracts")
        .select(
            "*, regions(id, name), entities(id, name), projects(id, name), counterparties(id, legal_name, status)"
        )
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    return r.data[0]


def upload_contract(
    supabase: Client,
    *,
    region_id: UUID,
    entity_id: UUID,
    project_id: UUID,
    counterparty_id: UUID,
    contract_type: str,
    title: str | None,
    storage_path: str,
    file_name: str,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("contracts")
        .insert({
            "region_id": str(region_id),
            "entity_id": str(entity_id),
            "project_id": str(project_id),
            "counterparty_id": str(counterparty_id),
            "contract_type": contract_type,
            "title": title or file_name,
            "storage_path": storage_path,
            "file_name": file_name,
            "created_by": actor.id if actor else None,
        })
        .execute()
    )
    data = row.data[0] if row.data else {}
    return data


async def audit_download(
    supabase: Client,
    contract_id: str,
    actor: CurrentUser | None,
) -> None:
    await audit_log(
        supabase,
        action="contract.download",
        resource_type="contract",
        resource_id=contract_id,
        actor=actor,
    )


async def update_contract(
    supabase: Client,
    id: UUID,
    payload: dict,
    actor: CurrentUser | None,
) -> dict | None:
    r = (
        supabase.table("contracts")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="contract.update",
        resource_type="contract",
        resource_id=str(id),
        details=payload,
        actor=actor,
    )
    return r.data[0]


async def delete_contract(
    supabase: Client,
    id: UUID,
    storage_path: str | None,
    actor: CurrentUser | None,
) -> bool:
    if storage_path:
        try:
            supabase.storage.from_(BUCKET).remove([storage_path])
        except Exception:
            pass
    r = supabase.table("contracts").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="contract.delete",
            resource_type="contract",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
