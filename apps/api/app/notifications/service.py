import structlog
from datetime import datetime, timezone

from supabase import Client

logger = structlog.get_logger()


def list_notifications(
    supabase: Client,
    recipient_email: str | None = None,
    status: str | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list[dict], int]:
    query = supabase.table("notifications").select("*", count="exact")
    if recipient_email:
        query = query.eq("recipient_email", recipient_email)
    if status:
        query = query.eq("status", status)
    result = query.order("created_at", desc=True).range(offset, offset + limit - 1).execute()
    return result.data, result.count or 0


async def send_pending_notifications(supabase: Client) -> int:
    from app.config import settings

    result = supabase.table("notifications").select("*").eq("status", "pending").limit(50).execute()
    sent_count = 0

    for notif in result.data or []:
        try:
            if notif["channel"] == "email" and settings.sendgrid_api_key:
                _send_email(
                    to=notif["recipient_email"],
                    subject=notif["subject"],
                    body=notif["body"],
                    api_key=settings.sendgrid_api_key,
                    from_email=settings.notification_from_email,
                )
                supabase.table("notifications").update(
                    {
                        "status": "sent",
                        "sent_at": datetime.now(timezone.utc).isoformat(),
                    }
                ).eq("id", notif["id"]).execute()
                sent_count += 1
            else:
                logger.info(
                    "notification_logged",
                    channel=notif["channel"],
                    recipient=notif.get("recipient_email"),
                    subject=notif["subject"],
                )
                supabase.table("notifications").update(
                    {
                        "status": "sent",
                        "sent_at": datetime.now(timezone.utc).isoformat(),
                    }
                ).eq("id", notif["id"]).execute()
                sent_count += 1
        except Exception as e:
            logger.error("notification_send_failed", notification_id=notif["id"], error=str(e))
            supabase.table("notifications").update(
                {"status": "failed", "error_message": str(e)}
            ).eq("id", notif["id"]).execute()

    logger.info("notifications_processed", sent=sent_count)
    return sent_count


def _send_email(to: str, subject: str, body: str, api_key: str, from_email: str):
    import httpx

    response = httpx.post(
        "https://api.sendgrid.com/v3/mail/send",
        headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
        json={
            "personalizations": [{"to": [{"email": to}]}],
            "from": {"email": from_email},
            "subject": subject,
            "content": [{"type": "text/plain", "value": body}],
        },
    )
    response.raise_for_status()
