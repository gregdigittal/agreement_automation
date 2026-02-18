from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.escalation import service
from app.escalation.schemas import CreateEscalationRuleInput, UpdateEscalationRuleInput

router = APIRouter(tags=["escalation"])


@router.get("/workflow-templates/{id}/escalation-rules")
async def list_escalation_rules(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_rules(supabase, str(id))


@router.post(
    "/workflow-templates/{id}/escalation-rules",
    dependencies=[Depends(require_roles("System Admin"))],
    status_code=201,
)
async def create_escalation_rule(
    id: UUID,
    body: CreateEscalationRuleInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_rule(supabase, str(id), body, user)


@router.patch(
    "/escalation-rules/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def update_escalation_rule(
    id: UUID,
    body: UpdateEscalationRuleInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_rule(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Rule not found")
    return row


@router.delete(
    "/escalation-rules/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def delete_escalation_rule(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_rule(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Rule not found")
    return {"ok": True}


@router.get("/escalations/active")
async def list_active_escalations(
    response: Response,
    limit: int = 50,
    offset: int = 0,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_active_escalations(supabase, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.post(
    "/escalations/{id}/resolve",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def resolve_escalation(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.resolve_escalation(supabase, str(id), user)
    if not row:
        raise HTTPException(status_code=404, detail="Escalation not found or already resolved")
    return row
