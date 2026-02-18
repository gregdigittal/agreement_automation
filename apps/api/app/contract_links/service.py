from datetime import datetime, timezone
from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser


def _get_contract(supabase: Client, contract_id: UUID) -> dict | None:
    r = supabase.table("contracts").select("*").eq("id", str(contract_id)).execute()
    return r.data[0] if r.data else None


def _create_child_contract(
    supabase: Client,
    parent: dict,
    *,
    link_type: str,
    actor: CurrentUser | None,
) -> dict:
    now = datetime.now(timezone.utc).isoformat()
    row = (
        supabase.table("contracts")
        .insert(
            {
                "region_id": parent["region_id"],
                "entity_id": parent["entity_id"],
                "project_id": parent["project_id"],
                "counterparty_id": parent["counterparty_id"],
                "contract_type": parent["contract_type"],
                "title": f"{parent.get('title') or 'Contract'} ({link_type})",
                "workflow_state": "draft",
                "created_at": now,
                "created_by": actor.id if actor else None,
                "parent_contract_id": parent["id"],
            }
        )
        .execute()
    )
    return row.data[0] if row.data else {}


async def create_link(
    supabase: Client,
    parent_id: UUID,
    link_type: str,
    actor: CurrentUser | None,
) -> dict:
    parent = _get_contract(supabase, parent_id)
    if not parent:
        raise ValueError("Parent contract not found")
    child = _create_child_contract(supabase, parent, link_type=link_type, actor=actor)
    supabase.table("contract_links").insert(
        {"parent_contract_id": str(parent_id), "child_contract_id": child.get("id"), "link_type": link_type}
    ).execute()
    await audit_log(
        supabase,
        action="contract.link.create",
        resource_type="contract_link",
        resource_id=child.get("id"),
        details={"link_type": link_type},
        actor=actor,
    )
    return child


async def create_extension(
    supabase: Client, parent_id: UUID, actor: CurrentUser | None
) -> dict:
    parent = _get_contract(supabase, parent_id)
    if not parent:
        raise ValueError("Parent contract not found")
    await audit_log(
        supabase,
        action="contract.renewal.extend",
        resource_type="contract",
        resource_id=str(parent_id),
        actor=actor,
    )
    return parent


def list_linked(supabase: Client, parent_id: UUID) -> dict:
    links = (
        supabase.table("contract_links")
        .select("*")
        .eq("parent_contract_id", str(parent_id))
        .execute()
        .data
        or []
    )
    result = {"amendment": [], "renewal": [], "side_letter": [], "addendum": []}
    for link in links:
        child = _get_contract(supabase, UUID(link["child_contract_id"]))
        if child:
            result.setdefault(link["link_type"], []).append(child)
    return result
