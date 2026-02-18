from uuid import UUID

from fastapi import APIRouter, Depends, Header, HTTPException, Query

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.config import settings
from app.deps import get_supabase
from app.merchant_agreements.schemas import GenerateMerchantAgreementInput
from app.merchant_agreements.service import generate_merchant_agreement, tito_validate
from supabase import Client

router = APIRouter(tags=["merchant_agreements"])


@router.post(
    "/merchant-agreements/generate",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def merchant_agreement_generate(
    body: GenerateMerchantAgreementInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await generate_merchant_agreement(
            supabase,
            template_id=body.template_id,
            vendor_name=body.vendor_name,
            merchant_fee=body.merchant_fee,
            region_id=body.region_id,
            entity_id=body.entity_id,
            project_id=body.project_id,
            counterparty_id=body.counterparty_id,
            region_terms=body.region_terms,
            actor=user,
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/tito/validate")
async def merchant_tito_validate(
    vendor: str | None = None,
    entity_id: str | None = Query(None, alias="entity_id"),
    region_id: str | None = Query(None, alias="region_id"),
    project_id: str | None = Query(None, alias="project_id"),
    x_api_key: str | None = Header(None, alias="X-API-Key"),
    supabase: Client = Depends(get_supabase),
):
    if not settings.tito_api_key:
        raise HTTPException(status_code=503, detail="TiTo API key not configured")
    if x_api_key != settings.tito_api_key:
        raise HTTPException(status_code=401, detail="Invalid API key")
    return await tito_validate(
        supabase,
        vendor=vendor,
        entity_id=entity_id,
        region_id=region_id,
        project_id=project_id,
        actor=None,
    )
