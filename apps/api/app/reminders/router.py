from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.reminders import service
from app.reminders.schemas import CreateReminderInput, UpdateReminderInput

router = APIRouter(tags=["reminders"])


@router.get("/contracts/{id}/reminders")
async def list_reminders(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_reminders(supabase, str(id))


@router.post(
    "/contracts/{id}/reminders",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    status_code=201,
)
async def create_reminder(
    id: UUID,
    body: CreateReminderInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_reminder(supabase, str(id), body, user)


@router.patch(
    "/reminders/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def update_reminder(
    id: UUID,
    body: UpdateReminderInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_reminder(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Reminder not found")
    return row


@router.delete(
    "/reminders/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def delete_reminder(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_reminder(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Reminder not found")
    return {"ok": True}
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.reminders.schemas import CreateReminderInput, UpdateReminderInput
from app.reminders import service

router = APIRouter(tags=["reminders"])


@router.get("/contracts/{id}/reminders")
async def list_reminders(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_reminders(supabase, str(id))


@router.post(
    "/contracts/{id}/reminders",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    status_code=201,
)
async def create_reminder(
    id: UUID,
    body: CreateReminderInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_reminder(supabase, str(id), body, user)


@router.patch(
    "/reminders/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def update_reminder(
    id: UUID,
    body: UpdateReminderInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_reminder(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Reminder not found")
    return row


@router.delete(
    "/reminders/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def delete_reminder(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_reminder(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Reminder not found")
    return {"ok": True}
