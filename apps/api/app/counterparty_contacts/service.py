from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.counterparty_contacts.schemas import CreateContactInput, UpdateContactInput


async def create(
    supabase: Client,
    counterparty_id: UUID,
    body: CreateContactInput,
    actor: CurrentUser | None,
) -> dict:
    row = (
        supabase.table("counterparty_contacts")
        .insert({
            "counterparty_id": str(counterparty_id),
            "name": body.name,
            "email": body.email,
            "role": body.role,
            "is_signer": body.is_signer,
        })
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="counterparty_contact.create",
        resource_type="counterparty_contact",
        resource_id=data.get("id"),
        details={"name": body.name},
        actor=actor,
    )
    return data


def list_for_counterparty(supabase: Client, counterparty_id: UUID) -> list:
    r = (
        supabase.table("counterparty_contacts")
        .select("*")
        .eq("counterparty_id", str(counterparty_id))
        .order("name")
        .execute()
    )
    return r.data or []


def get_by_id(supabase: Client, id: UUID) -> dict | None:
    r = supabase.table("counterparty_contacts").select("*").eq("id", str(id)).execute()
    if not r.data:
        return None
    return r.data[0]


async def update(
    supabase: Client,
    id: UUID,
    body: UpdateContactInput,
    actor: CurrentUser | None,
) -> dict | None:
    payload = body.model_dump(exclude_unset=True)
    if not payload:
        return get_by_id(supabase, id)
    r = (
        supabase.table("counterparty_contacts")
        .update(payload)
        .eq("id", str(id))
        .execute()
    )
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="counterparty_contact.update",
        resource_type="counterparty_contact",
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
    r = supabase.table("counterparty_contacts").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="counterparty_contact.delete",
            resource_type="counterparty_contact",
            resource_id=str(id),
            actor=actor,
        )
    return deleted
