from uuid import UUID

from fastapi import APIRouter, Depends, File, HTTPException, Query, UploadFile
from fastapi.responses import JSONResponse

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.wiki_contracts.schemas import CreateWikiContractInput, UpdateWikiContractInput
from app.wiki_contracts.service import (
    get_by_id,
    get_download_url,
    list_all,
    publish,
    create,
    update,
    delete,
    upload_file,
)
from supabase import Client

router = APIRouter(tags=["wiki_contracts"])


@router.post("/wiki-contracts", dependencies=[Depends(require_roles("System Admin", "Legal"))])
async def wiki_contract_create(
    body: CreateWikiContractInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await create(supabase, body, user)


@router.get("/wiki-contracts")
async def wiki_contract_list(
    status: str | None = None,
    region_id: UUID | None = Query(None, alias="regionId"),
    category: str | None = None,
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_all(
        supabase, status=status, region_id=region_id, category=category, limit=limit, offset=offset
    )
    resp = JSONResponse(content=items)
    resp.headers["X-Total-Count"] = str(total)
    return resp


@router.get("/wiki-contracts/{id}")
async def wiki_contract_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Wiki contract not found")
    return row


@router.patch("/wiki-contracts/{id}", dependencies=[Depends(require_roles("System Admin", "Legal"))])
async def wiki_contract_update(
    id: UUID,
    body: UpdateWikiContractInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await update(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Wiki contract not found")
    return row


@router.patch("/wiki-contracts/{id}/publish", dependencies=[Depends(require_roles("System Admin"))])
async def wiki_contract_publish(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await publish(supabase, id, user)
    if not row:
        raise HTTPException(status_code=404, detail="Wiki contract not found")
    return row


@router.delete("/wiki-contracts/{id}", dependencies=[Depends(require_roles("System Admin"))])
async def wiki_contract_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await delete(supabase, id, user)
    if not ok:
        raise HTTPException(status_code=404, detail="Wiki contract not found")
    return {"ok": True}


@router.post(
    "/wiki-contracts/{id}/upload",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def wiki_contract_upload(
    id: UUID,
    file: UploadFile = File(...),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    content_type = (file.content_type or "").strip().lower()
    body = await file.read()
    try:
        row = await upload_file(
            supabase,
            id=id,
            file_name=(file.filename or "template"),
            content_type=content_type,
            file_bytes=body,
            actor=user,
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    if not row:
        raise HTTPException(status_code=404, detail="Wiki contract not found")
    return row


@router.get("/wiki-contracts/{id}/download-url")
async def wiki_contract_download(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Wiki contract not found")
    if not row.get("storage_path"):
        raise HTTPException(status_code=404, detail="File not found")
    url = get_download_url(supabase, row["storage_path"])
    return {"url": url}
