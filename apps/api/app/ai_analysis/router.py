from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException
from supabase import Client

from app.ai_analysis import service
from app.ai_analysis.schemas import CorrectFieldInput, TriggerAnalysisInput, VerifyFieldInput
from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase

router = APIRouter(tags=["ai_analysis"])


@router.post(
    "/contracts/{id}/analyze",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def trigger_analysis(
    id: UUID,
    body: TriggerAnalysisInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await service.trigger_analysis(supabase, str(id), body.analysis_type, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/contracts/{id}/analysis")
async def get_analysis(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    analyses = await service.get_analyses(supabase, str(id))
    fields = await service.get_extracted_fields(supabase, str(id))
    return {"analyses": analyses, "extracted_fields": fields}


@router.post(
    "/ai-fields/{field_id}/verify",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def verify_field(
    field_id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.verify_field(supabase, str(field_id), user)
    if not row:
        raise HTTPException(status_code=404, detail="Field not found")
    return row


@router.patch(
    "/ai-fields/{field_id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def correct_field(
    field_id: UUID,
    body: CorrectFieldInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.correct_field(supabase, str(field_id), body.field_value, user)
    if not row:
        raise HTTPException(status_code=404, detail="Field not found")
    return row
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException
from supabase import Client

from app.ai_analysis.schemas import CorrectFieldInput, TriggerAnalysisInput, VerifyFieldInput
from app.ai_analysis import service
from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase

router = APIRouter(tags=["ai_analysis"])


@router.post(
    "/contracts/{id}/analyze",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def trigger_analysis(
    id: UUID,
    body: TriggerAnalysisInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await service.trigger_analysis(supabase, str(id), body.analysis_type, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/contracts/{id}/analysis")
async def get_analysis(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    analyses = await service.get_analyses(supabase, str(id))
    fields = await service.get_extracted_fields(supabase, str(id))
    return {"analyses": analyses, "extracted_fields": fields}


@router.post(
    "/ai-fields/{field_id}/verify",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def verify_field(
    field_id: UUID,
    body: VerifyFieldInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.verify_field(supabase, str(field_id), user)
    if not row:
        raise HTTPException(status_code=404, detail="Field not found")
    return row


@router.patch(
    "/ai-fields/{field_id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def correct_field(
    field_id: UUID,
    body: CorrectFieldInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.correct_field(supabase, str(field_id), body.field_value, user)
    if not row:
        raise HTTPException(status_code=404, detail="Field not found")
    return row
