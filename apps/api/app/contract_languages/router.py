from uuid import UUID

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.contract_languages import service
from app.deps import get_supabase

router = APIRouter(tags=["contract_languages"])


@router.get("/contracts/{id}/languages")
async def list_languages(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_languages(supabase, str(id))


@router.post(
    "/contracts/{id}/languages",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
    status_code=201,
)
async def attach_language(
    id: UUID,
    language_code: str = Form(...),
    is_primary: bool = Form(False),
    file: UploadFile = File(...),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await service.attach_language(
            supabase, str(id), language_code, is_primary, file, user
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.delete(
    "/contract-languages/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def delete_language(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_language(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Language version not found")
    return {"ok": True}


@router.get("/contract-languages/{id}/download-url")
async def language_download_url(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    url = service.get_download_url(supabase, str(id))
    if not url:
        raise HTTPException(status_code=404, detail="Language file not found")
    return {"url": url}
from uuid import UUID

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.contract_languages import service

router = APIRouter(tags=["contract_languages"])


@router.get("/contracts/{id}/languages")
async def list_languages(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_languages(supabase, str(id))


@router.post(
    "/contracts/{id}/languages",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
    status_code=201,
)
async def attach_language(
    id: UUID,
    language_code: str = Form(...),
    is_primary: bool = Form(False),
    file: UploadFile = File(...),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await service.attach_language(supabase, str(id), language_code, is_primary, file, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.delete(
    "/contract-languages/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def delete_language(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_language(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Language version not found")
    return {"ok": True}
