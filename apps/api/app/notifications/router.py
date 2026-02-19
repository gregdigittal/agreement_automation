from datetime import datetime, timezone
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.notifications import service
from app.schemas.responses import NotificationOut

router = APIRouter(tags=["notifications"])


@router.get("/notifications", response_model=list[NotificationOut])
async def list_notifications(
    response: Response,
    status: str | None = None,
    limit: int = 50,
    offset: int = 0,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_notifications(supabase, user.email, status, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.get("/notifications/unread-count")
async def notification_unread_count(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = (
        supabase.table("notifications")
        .select("id", count="exact")
        .eq("recipient_email", user.email)
        .eq("status", "sent")
        .is_("read_at", "null")
        .execute()
    )
    return {"count": result.count or 0}


@router.patch("/notifications/{id}/read", response_model=NotificationOut)
async def notification_mark_read(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = (
        supabase.table("notifications")
        .update({"read_at": datetime.now(timezone.utc).isoformat()})
        .eq("id", str(id))
        .eq("recipient_email", user.email)
        .execute()
    )
    if not result.data:
        raise HTTPException(status_code=404, detail="Notification not found")
    return result.data[0]


@router.post("/notifications/mark-all-read")
async def notification_mark_all_read(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    supabase.table("notifications").update(
        {"read_at": datetime.now(timezone.utc).isoformat()}
    ).eq("recipient_email", user.email).is_("read_at", "null").execute()
    return {"ok": True}
