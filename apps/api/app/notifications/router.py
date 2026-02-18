from fastapi import APIRouter, Depends, Response
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.notifications import service

router = APIRouter(tags=["notifications"])


@router.get("/notifications")
async def list_notifications(
    status: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_notifications(supabase, user.email, status, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data
from fastapi import APIRouter, Depends, Response
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.notifications import service

router = APIRouter(tags=["notifications"])


@router.get("/notifications")
async def list_notifications(
    status: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_notifications(supabase, user.email, status, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data
