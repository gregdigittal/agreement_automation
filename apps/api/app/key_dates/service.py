from datetime import datetime, timezone
from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.key_dates.schemas import CreateKeyDateInput, UpdateKeyDateInput


async def create(
    supabase: Client,
    contract_id: UUID,
    body: CreateKeyDateInput,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("contract_key_dates")
        .insert(
            {
                "contract_id": str(contract_id),
                "date_type": body.date_type,
                "date_value": body.date_value.isoformat(),
                "description": body.description,
                "reminder_days": body.reminder_days,
            }
        )
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="key_date.create",
        resource_type="contract_key_date",
        resource_id=data.get("id"),
        actor=actor,
    )
    return data


def list_for_contract(supabase: Client, contract_id: UUID) -> list:
    r = (
        supabase.table("contract_key_dates")
        .select("*")
        .eq("contract_id", str(contract_id))
        .order("date_value")
        .execute()
    )
    return r.data or []


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = supabase.table("contract_key_dates").select("*").eq("id", str(id)).execute()
    return r.data[0] if r.data else None


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateKeyDateInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True)
    if "date_value" in payload and payload["date_value"] is not None:
        payload["date_value"] = payload["date_value"].isoformat()
    if not payload:
        return get_by_id(supabase, id)
    r = supabase.table("contract_key_dates").update(payload).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="key_date.update",
        resource_type="contract_key_date",
        resource_id=str(id),
        actor=actor,
    )
    return r.data[0]


async def verify(
    supabase: Client,
    id: UUID,
    actor: CurrentUser | None,
) -> dict | None:
    payload = {
        "is_verified": True,
        "verified_by": actor.id if actor else None,
        "verified_at": datetime.now(timezone.utc).isoformat(),
    }
    r = supabase.table("contract_key_dates").update(payload).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="key_date.verify",
        resource_type="contract_key_date",
        resource_id=str(id),
        actor=actor,
    )
    return r.data[0]


async def delete(
    supabase: Client,
    id: UUID,
    actor: CurrentUser | None,
) -> bool:
    r = supabase.table("contract_key_dates").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="key_date.delete",
            resource_type="contract_key_date",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
