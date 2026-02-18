from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import JSONResponse

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.counterparties.schemas import (
    CreateCounterpartyInput,
    StatusChangeInput,
    UpdateCounterpartyInput,
)
from app.counterparties.service import (
    change_status,
    create,
    delete,
    find_duplicates,
    get_by_id,
    list_all,
    update,
)
from app.deps import get_supabase
from supabase import Client

router = APIRouter(tags=["counterparties"])


@router.get(
    "/counterparties/duplicates",
    dependencies=[Depends(get_current_user)],
)
async def counterparty_duplicates(
    legal_name: str = Query(..., alias="legalName"),
    registration_number: str | None = Query(None, alias="registrationNumber"),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return find_duplicates(supabase, legal_name, registration_number)


@router.post(
    "/counterparties",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def counterparty_create(
    body: CreateCounterpartyInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await create(supabase, body, user)
    except Exception as e:
        err = str(e).lower()
        if "duplicate" in err or "unique" in err:
            raise HTTPException(status_code=409, detail="Conflict.")
        raise


@router.get("/counterparties")
async def counterparty_list(
    status: str | None = None,
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_all(supabase, status=status, limit=limit, offset=offset)
    resp = JSONResponse(content=items)
    resp.headers["X-Total-Count"] = str(total)
    return resp


@router.get("/counterparties/{id}")
async def counterparty_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Counterparty not found")
    return row


@router.patch(
    "/counterparties/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def counterparty_update(
    id: UUID,
    body: UpdateCounterpartyInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await update(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Counterparty not found")
    return row


@router.patch(
    "/counterparties/{id}/status",
    dependencies=[Depends(require_roles("Legal"))],
)
async def counterparty_status(
    id: UUID,
    body: StatusChangeInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await change_status(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Counterparty not found")
    return row


@router.delete(
    "/counterparties/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def counterparty_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        ok = await delete(supabase, id, user)
        if not ok:
            raise HTTPException(status_code=404, detail="Counterparty not found")
        return {"ok": True}
    except HTTPException:
        raise
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        err = str(e).lower()
        if "foreign key" in err or "violates" in err:
            raise HTTPException(status_code=409, detail="Counterparty is in use.")
        raise
