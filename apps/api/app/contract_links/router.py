from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.contract_links.schemas import RenewalInput
from app.contract_links.service import create_extension, create_link, list_linked
from app.deps import get_supabase
from supabase import Client

router = APIRouter(tags=["contract_links"])


@router.post(
    "/contracts/{id}/amendments",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def create_amendment(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await create_link(supabase, id, "amendment", user)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))


@router.post(
    "/contracts/{id}/renewals",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def create_renewal(
    id: UUID,
    body: RenewalInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        if body.type == "extension":
            return await create_extension(supabase, id, user)
        return await create_link(supabase, id, "renewal", user)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))


@router.post(
    "/contracts/{id}/side-letters",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def create_side_letter(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await create_link(supabase, id, "side_letter", user)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))


@router.get("/contracts/{id}/linked")
async def get_linked_contracts(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return list_linked(supabase, id)
