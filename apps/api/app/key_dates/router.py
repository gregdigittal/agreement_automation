from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.key_dates.schemas import CreateKeyDateInput, UpdateKeyDateInput
from app.key_dates.service import create, delete, get_by_id, list_for_contract, update, verify
from supabase import Client

router = APIRouter(tags=["key_dates"])


@router.post(
    "/contracts/{id}/key-dates",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def key_date_create(
    id: UUID,
    body: CreateKeyDateInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await create(supabase, id, body, user)


@router.get("/contracts/{id}/key-dates")
async def key_date_list(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return list_for_contract(supabase, id)


@router.patch("/key-dates/{id}", dependencies=[Depends(require_roles("System Admin", "Legal"))])
async def key_date_update(
    id: UUID,
    body: UpdateKeyDateInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await update(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Key date not found")
    return row


@router.patch("/key-dates/{id}/verify", dependencies=[Depends(require_roles("Legal"))])
async def key_date_verify(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await verify(supabase, id, user)
    if not row:
        raise HTTPException(status_code=404, detail="Key date not found")
    return row


@router.delete("/key-dates/{id}", dependencies=[Depends(require_roles("System Admin"))])
async def key_date_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await delete(supabase, id, user)
    if not ok:
        raise HTTPException(status_code=404, detail="Key date not found")
    return {"ok": True}
