from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, Response

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.counterparties.schemas import (
    CreateCounterpartyInput,
    MergeCounterpartyInput,
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
    merge_counterparties,
    update,
)
from app.deps import get_supabase
from app.schemas.responses import CounterpartyOut
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
    response_model=CounterpartyOut,
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


@router.get("/counterparties", response_model=list[CounterpartyOut])
async def counterparty_list(
    response: Response,
    search: str | None = None,
    status: str | None = None,
    limit: int = Query(25, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_all(supabase, search=search, status=status, limit=limit, offset=offset)
    response.headers["X-Total-Count"] = str(total)
    return items


@router.get("/counterparties/{id}", response_model=CounterpartyOut)
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
    response_model=CounterpartyOut,
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
    response_model=CounterpartyOut,
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


@router.post(
    "/counterparties/{id}/merge",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def counterparty_merge(
    id: UUID,
    body: MergeCounterpartyInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await merge_counterparties(supabase, body.source_id, id, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


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
