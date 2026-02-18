from datetime import datetime, timezone
from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.counterparties.schemas import (
    CreateCounterpartyInput,
    StatusChangeInput,
    UpdateCounterpartyInput,
)


async def create(
    supabase: Client,
    body: CreateCounterpartyInput,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("counterparties")
        .insert({
            "legal_name": body.legal_name,
            "registration_number": body.registration_number,
            "address": body.address,
            "jurisdiction": body.jurisdiction,
            "preferred_language": body.preferred_language,
        })
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="counterparty.create",
        resource_type="counterparty",
        resource_id=data.get("id"),
        details={"legal_name": body.legal_name},
        actor=actor,
    )
    return data


def list_all(
    supabase: Client,
    status: str | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("counterparties")
        .select("*", count="exact")
        .order("legal_name")
        .range(offset, offset + limit - 1)
    )
    if status:
        q = q.eq("status", status)
    r = q.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = (
        supabase.table("counterparties")
        .select("*, counterparty_contacts(*)")
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    return r.data[0]


def find_duplicates(
    supabase: Client,
    legal_name: str,
    registration_number: str | None = None,
) -> list[dict]:
    name = (legal_name or "").strip()
    if not name:
        return []
    q = (
        supabase.table("counterparties")
        .select("id, legal_name, registration_number")
        .ilike("legal_name", f"%{name}%")
    )
    r = q.execute()
    rows = list(r.data or [])
    if registration_number and registration_number.strip():
        r2 = (
            supabase.table("counterparties")
            .select("id, legal_name, registration_number")
            .eq("registration_number", registration_number.strip())
            .execute()
        )
        for row in r2.data or []:
            if not any(x["id"] == row["id"] for x in rows):
                rows.append(row)
    seen = set()
    out = []
    for row in rows:
        if row["id"] in seen:
            continue
        seen.add(row["id"])
        out.append({
            "id": row["id"],
            "legal_name": row["legal_name"],
            "registration_number": row.get("registration_number"),
        })
    return out


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateCounterpartyInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True)
    if not payload:
        return get_by_id(supabase, id)
    r = (
        supabase.table("counterparties")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="counterparty.update",
        resource_type="counterparty",
        resource_id=str(id),
        details=payload,
        actor=actor,
    )
    return r.data[0]


async def change_status(
    supabase: Client,
    id: UUID,
    body: StatusChangeInput,
    actor: CurrentUser | None,
) -> dict | None:
    current = supabase.table("counterparties").select("status, status_reason").eq("id", str(id)).execute()
    if not current.data:
        return None
    prev = current.data[0].get("status")
    payload = {
        "status": body.status,
        "status_reason": body.reason,
        "status_changed_at": datetime.now(timezone.utc).isoformat(),
        "status_changed_by": actor.id if actor else None,
        "supporting_document_ref": body.supporting_document_ref,
    }
    r = (
        supabase.table("counterparties")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="counterparty.status_change",
        resource_type="counterparty",
        resource_id=str(id),
        details={
            "previous_status": prev,
            "new_status": body.status,
            "reason": body.reason,
        },
        actor=actor,
    )
    return r.data[0]


async def delete(
    supabase: Client,
    id: UUID,
    actor: CurrentUser | None,
) -> bool:
    contracts = (
        supabase.table("contracts")
        .select("id")
        .eq("counterparty_id", str(id))
        .limit(1)
        .execute()
    )
    if contracts.data:
        raise ValueError("Cannot delete counterparty: it has associated contracts")
    r = supabase.table("counterparties").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="counterparty.delete",
            resource_type="counterparty",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
