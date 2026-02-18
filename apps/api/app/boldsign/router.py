from uuid import UUID

import hashlib
import hmac

from fastapi import APIRouter, Depends, HTTPException, Request

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.boldsign.schemas import BoldsignWebhookPayload
from app.boldsign.service import get_signing_status, handle_webhook, send_to_sign
from app.config import settings
from app.deps import get_supabase
from supabase import Client

router = APIRouter(tags=["boldsign"])


@router.post(
    "/contracts/{id}/send-to-sign",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def send_contract_to_sign(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await send_to_sign(supabase, id, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/contracts/{id}/signing-status")
async def signing_status(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return get_signing_status(supabase, id) or {}


@router.post("/webhooks/boldsign")
async def boldsign_webhook(request: Request, supabase: Client = Depends(get_supabase)):
    body_bytes = await request.body()
    if settings.boldsign_api_key:
        signature = request.headers.get("X-BoldSign-Signature", "")
        expected = hmac.new(
            settings.boldsign_api_key.encode(), body_bytes, hashlib.sha256
        ).hexdigest()
        if not hmac.compare_digest(signature, expected):
            raise HTTPException(status_code=401, detail="Invalid webhook signature")
    payload = await request.json()
    await handle_webhook(supabase, payload)
    return {"ok": True}
