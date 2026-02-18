from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.obligations import service
from app.obligations.schemas import CreateObligationInput, UpdateObligationInput

router = APIRouter(tags=["obligations"])


@router.get("/contracts/{id}/obligations")
async def list_contract_obligations(
    id: UUID,
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_obligations(supabase, str(id), status, obligation_type, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.get("/obligations")
async def list_all_obligations(
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_obligations(supabase, None, status, obligation_type, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.post(
    "/contracts/{id}/obligations",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    status_code=201,
)
async def create_obligation(
    id: UUID,
    body: CreateObligationInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_obligation(supabase, str(id), body, user)


@router.patch(
    "/obligations/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def update_obligation(
    id: UUID,
    body: UpdateObligationInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_obligation(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Obligation not found")
    return row


@router.delete(
    "/obligations/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def delete_obligation(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_obligation(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Obligation not found")
    return {"ok": True}
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.obligations.schemas import CreateObligationInput, UpdateObligationInput
from app.obligations import service

router = APIRouter(tags=["obligations"])


@router.get("/contracts/{id}/obligations")
async def list_contract_obligations(
    id: UUID,
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_obligations(supabase, str(id), status, obligation_type, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.get("/obligations")
async def list_all_obligations(
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_obligations(supabase, None, status, obligation_type, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.post(
    "/contracts/{id}/obligations",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    status_code=201,
)
async def create_obligation(
    id: UUID,
    body: CreateObligationInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_obligation(supabase, str(id), body, user)


@router.patch(
    "/obligations/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def update_obligation(
    id: UUID,
    body: UpdateObligationInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_obligation(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Obligation not found")
    return row


@router.delete(
    "/obligations/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def delete_obligation(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_obligation(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Obligation not found")
    return {"ok": True}
