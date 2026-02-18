from fastapi import APIRouter, Depends
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.reports import service

router = APIRouter(prefix="/reports", tags=["reports"])


@router.get("/contract-status")
async def contract_status(
    region_id: str | None = None,
    entity_id: str | None = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.contract_status_summary(supabase, region_id, entity_id)


@router.get("/expiry-horizon")
async def expiry_horizon(
    region_id: str | None = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.expiry_horizon(supabase, region_id)


@router.get("/signing-status")
async def signing_status(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.signing_status_summary(supabase)


@router.get("/ai-costs")
async def ai_costs(
    period_days: int = 30,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.ai_cost_summary(supabase, period_days)
from fastapi import APIRouter, Depends
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.reports import service

router = APIRouter(prefix="/reports", tags=["reports"])


@router.get("/contract-status")
async def contract_status(
    region_id: str | None = None,
    entity_id: str | None = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.contract_status_summary(supabase, region_id, entity_id)


@router.get("/expiry-horizon")
async def expiry_horizon(
    region_id: str | None = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.expiry_horizon(supabase, region_id)


@router.get("/signing-status")
async def signing_status(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.signing_status_summary(supabase)


@router.get("/ai-costs")
async def ai_costs(
    period_days: int = 30,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.ai_cost_summary(supabase, period_days)
