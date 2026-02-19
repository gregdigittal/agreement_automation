from datetime import datetime
from uuid import UUID

from fastapi import APIRouter, Depends, Query, Response

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.schemas.responses import AuditLogOut
from supabase import Client

router = APIRouter(tags=["audit"])


@router.get(
    "/audit/resource/{resource_type}/{resource_id}",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Audit"))],
    response_model=list[AuditLogOut],
)
async def get_audit_for_resource(
    resource_type: str,
    resource_id: UUID,
    limit: int = Query(100, ge=1, le=500),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    q = (
        supabase.table("audit_log")
        .select("*")
        .eq("resource_type", resource_type)
        .eq("resource_id", str(resource_id))
        .order("at", desc=True)
        .limit(limit)
    )
    r = q.execute()
    rows = r.data if hasattr(r, "data") else []
    return rows


@router.get(
    "/audit/export",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Audit"))],
    response_model=list[AuditLogOut],
)
async def audit_export(
    response: Response,
    from_date: datetime | None = Query(None, alias="from"),
    to_date: datetime | None = Query(None, alias="to"),
    resource_type: str | None = None,
    actor_id: str | None = None,
    limit: int = Query(25, ge=1, le=50000),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    q = supabase.table("audit_log").select("*", count="exact").order("at", desc=True)
    if from_date is not None:
        q = q.gte("at", from_date.isoformat())
    if to_date is not None:
        q = q.lte("at", to_date.isoformat())
    if resource_type:
        q = q.eq("resource_type", resource_type)
    if actor_id:
        q = q.eq("actor_id", actor_id)
    r = q.range(offset, offset + limit - 1).execute()
    rows = r.data if hasattr(r, "data") else []
    response.headers["X-Total-Count"] = str(r.count or 0)
    return rows
