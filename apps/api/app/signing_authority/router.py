from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import JSONResponse

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.signing_authority.schemas import CreateSigningAuthorityInput, UpdateSigningAuthorityInput
from app.signing_authority.service import create, delete, get_by_id, list_all, update
from supabase import Client

router = APIRouter(tags=["signing_authority"])


@router.post(
    "/signing-authority",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def signing_authority_create(
    body: CreateSigningAuthorityInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data = await create(supabase, body, user)
    return data


@router.get("/signing-authority")
async def signing_authority_list(
    entity_id: UUID | None = Query(None, alias="entityId"),
    project_id: UUID | None = Query(None, alias="projectId"),
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_all(
        supabase, entity_id=entity_id, project_id=project_id, limit=limit, offset=offset
    )
    resp = JSONResponse(content=items)
    resp.headers["X-Total-Count"] = str(total)
    return resp


@router.get("/signing-authority/{id}")
async def signing_authority_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Signing authority not found")
    return row


@router.patch(
    "/signing-authority/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def signing_authority_update(
    id: UUID,
    body: UpdateSigningAuthorityInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await update(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Signing authority not found")
    return row


@router.delete(
    "/signing-authority/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def signing_authority_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await delete(supabase, id, user)
    if not ok:
        raise HTTPException(status_code=404, detail="Signing authority not found")
    return {"ok": True}
