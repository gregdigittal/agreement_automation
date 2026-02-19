from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import JSONResponse

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.regions.schemas import CreateRegionInput, UpdateRegionInput
from app.regions.service import create, delete, get_by_id, list_all, update
from app.schemas.responses import RegionOut
from supabase import Client

router = APIRouter(tags=["regions"])


@router.post(
    "/regions",
    dependencies=[Depends(require_roles("System Admin"))],
    response_model=RegionOut,
)
async def region_create(
    body: CreateRegionInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        data = await create(supabase, body, user)
        return data
    except Exception as e:
        err = str(e).lower()
        if "foreign key" in err or "violates" in err:
            raise HTTPException(status_code=400, detail="Invalid reference.")
        if "duplicate" in err or "unique" in err:
            raise HTTPException(status_code=409, detail="Region with this code already exists.")
        raise


@router.get("/regions", response_model=list[RegionOut])
async def region_list(
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_all(supabase, limit=limit, offset=offset)
    resp = JSONResponse(content=items)
    resp.headers["X-Total-Count"] = str(total)
    return resp


@router.get("/regions/{id}", response_model=RegionOut)
async def region_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Region not found")
    return row


@router.patch(
    "/regions/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
    response_model=RegionOut,
)
async def region_update(
    id: UUID,
    body: UpdateRegionInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        row = await update(supabase, id, body, user)
        if not row:
            raise HTTPException(status_code=404, detail="Region not found")
        return row
    except Exception as e:
        err = str(e).lower()
        if "foreign key" in err or "violates" in err:
            raise HTTPException(status_code=400, detail="Invalid reference.")
        if "duplicate" in err or "unique" in err:
            raise HTTPException(status_code=409, detail="Conflict.")
        raise


@router.delete("/regions/{id}", dependencies=[Depends(require_roles("System Admin"))])
async def region_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        ok = await delete(supabase, id, user)
        if not ok:
            raise HTTPException(status_code=404, detail="Region not found")
        return {"ok": True}
    except HTTPException:
        raise
    except Exception as e:
        err = str(e).lower()
        if "foreign key" in err or "violates" in err:
            raise HTTPException(status_code=409, detail="Region is in use.")
        raise