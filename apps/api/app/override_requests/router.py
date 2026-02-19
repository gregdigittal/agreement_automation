from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.override_requests.schemas import CreateOverrideRequestInput, DecideOverrideRequestInput
from app.override_requests.service import create_request, decide, list_pending

router = APIRouter(tags=["override_requests"])


@router.post("/counterparties/{counterparty_id}/override-requests")
async def override_request_create(
    counterparty_id: UUID,
    body: CreateOverrideRequestInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await create_request(supabase, counterparty_id, body, user)


@router.get(
    "/override-requests",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def override_request_list(
    response: Response,
    limit: int = 25,
    offset: int = 0,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = list_pending(supabase, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.patch(
    "/override-requests/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def override_request_decide(
    id: UUID,
    body: DecideOverrideRequestInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await decide(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Override request not found or already decided")
    return row
