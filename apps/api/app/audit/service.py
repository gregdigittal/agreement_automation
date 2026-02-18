import structlog
from supabase import Client

from app.auth.models import CurrentUser

logger = structlog.get_logger()


async def audit_log(
    supabase: Client,
    *,
    action: str,
    resource_type: str,
    resource_id: str | None = None,
    details: dict | None = None,
    actor: CurrentUser | None = None,
) -> None:
    try:
        result = supabase.table("audit_log").insert(
            {
                "action": action,
                "resource_type": resource_type,
                "resource_id": resource_id,
                "details": details,
                "actor_id": actor.id if actor else None,
                "actor_email": actor.email if actor else None,
                "ip_address": actor.ip_address if actor else None,
            }
        ).execute()
        if hasattr(result, "error") and result.error:
            logger.error(
                "audit_log_insert_failed", action=action, error=str(result.error)
            )
    except Exception as e:
        logger.error("audit_log_exception", action=action, error=str(e))
