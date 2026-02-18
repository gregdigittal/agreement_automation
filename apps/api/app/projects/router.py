from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import JSONResponse

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.projects.schemas import CreateProjectInput, UpdateProjectInput
from app.projects.service import create, delete, get_by_id, list_all, update
from supabase import Client

router = APIRouter(tags=["projects"])


@router.post("/projects", dependencies=[Depends(require_roles("System Admin"))])
async def project_create(
    body: CreateProjectInput,
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
            raise HTTPException(status_code=409, detail="Conflict.")
        raise


@router.get("/projects")
async def project_list(
    entity_id: UUID | None = Query(None, alias="entityId"),
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_all(supabase, entity_id=entity_id, limit=limit, offset=offset)
    resp = JSONResponse(content=items)
    resp.headers["X-Total-Count"] = str(total)
    return resp


@router.get("/projects/{id}")
async def project_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Project not found")
    return row


@router.patch("/projects/{id}", dependencies=[Depends(require_roles("System Admin"))])
async def project_update(
    id: UUID,
    body: UpdateProjectInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        row = await update(supabase, id, body, user)
        if not row:
            raise HTTPException(status_code=404, detail="Project not found")
        return row
    except HTTPException:
        raise
    except Exception as e:
        err = str(e).lower()
        if "foreign key" in err or "violates" in err:
            raise HTTPException(status_code=400, detail="Invalid reference.")
        if "duplicate" in err or "unique" in err:
            raise HTTPException(status_code=409, detail="Conflict.")
        raise


@router.delete("/projects/{id}", dependencies=[Depends(require_roles("System Admin"))])
async def project_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        ok = await delete(supabase, id, user)
        if not ok:
            raise HTTPException(status_code=404, detail="Project not found")
        return {"ok": True}
    except HTTPException:
        raise
    except Exception as e:
        err = str(e).lower()
        if "foreign key" in err or "violates" in err:
            raise HTTPException(status_code=409, detail="Project is in use.")
        raise
