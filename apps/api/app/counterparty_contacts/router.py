from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.counterparty_contacts.schemas import CreateContactInput, UpdateContactInput
from app.counterparty_contacts.service import create, delete, get_by_id, list_for_counterparty, update
from app.deps import get_supabase
from supabase import Client

router = APIRouter(tags=["counterparty_contacts"])


@router.post(
    "/counterparties/{counterparty_id}/contacts",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def contact_create(
    counterparty_id: UUID,
    body: CreateContactInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data = await create(supabase, counterparty_id, body, user)
    return data


@router.get("/counterparties/{counterparty_id}/contacts")
async def contact_list(
    counterparty_id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return list_for_counterparty(supabase, counterparty_id)


@router.patch(
    "/counterparty-contacts/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def contact_update(
    id: UUID,
    body: UpdateContactInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await update(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Contact not found")
    return row


@router.delete(
    "/counterparty-contacts/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def contact_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await delete(supabase, id, user)
    if not ok:
        raise HTTPException(status_code=404, detail="Contact not found")
    return {"ok": True}
