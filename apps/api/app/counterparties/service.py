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
from app.notifications.helpers import notify_counterparty_status_change


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
    search: str | None = None,
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
    if search:
        q = q.or_(
            f"legal_name.ilike.%{search}%,registration_number.ilike.%{search}%"
        )
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
    row = r.data[0]
    await notify_counterparty_status_change(
        supabase,
        counterparty_id=id,
        counterparty_name=row.get("legal_name", "Unknown"),
        old_status=prev or "Unknown",
        new_status=body.status,
        reason=body.reason,
        changed_by=actor.email if actor else "system",
    )
    return row


async def merge_counterparties(
    supabase: Client,
    source_id: UUID,
    target_id: UUID,
    actor: CurrentUser,
) -> dict:
    source = (
        supabase.table("counterparties")
        .select("*")
        .eq("id", str(source_id))
        .single()
        .execute()
    )
    target = (
        supabase.table("counterparties")
        .select("*")
        .eq("id", str(target_id))
        .single()
        .execute()
    )
    if not source.data or not target.data:
        raise ValueError("Source or target counterparty not found")
    if str(source_id) == str(target_id):
        raise ValueError("Cannot merge a counterparty into itself")

    supabase.table("contracts").update({"counterparty_id": str(target_id)}).eq(
        "counterparty_id", str(source_id)
    ).execute()
    supabase.table("counterparty_contacts").update(
        {"counterparty_id": str(target_id)}
    ).eq("counterparty_id", str(source_id)).execute()

    supabase.table("counterparty_merges").insert(
        {
            "source_counterparty_id": str(source_id),
            "target_counterparty_id": str(target_id),
            "merged_by": actor.id,
            "merged_by_email": actor.email,
        }
    ).execute()

    supabase.table("counterparties").delete().eq("id", str(source_id)).execute()

    await audit_log(
        supabase,
        action="counterparty_merged",
        resource_type="counterparty",
        resource_id=str(target_id),
        details={
            "source_id": str(source_id),
            "source_name": source.data.get("legal_name"),
            "target_id": str(target_id),
            "target_name": target.data.get("legal_name"),
        },
        actor=actor,
    )

    return target.data


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
