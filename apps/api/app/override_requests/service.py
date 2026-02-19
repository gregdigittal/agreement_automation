from datetime import datetime, timezone
from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.notifications.helpers import create_notification


async def create_request(
    supabase: Client,
    counterparty_id: UUID,
    body,
    actor: CurrentUser,
) -> dict:
    row = {
        "counterparty_id": str(counterparty_id),
        "contract_title": body.contract_title,
        "requested_by": actor.id,
        "requested_by_email": actor.email,
        "reason": body.reason,
        "status": "pending",
    }
    result = supabase.table("override_requests").insert(row).execute()
    request_row = result.data[0]

    await create_notification(
        supabase,
        recipient_email="legal@digittal.com",
        channel="email",
        subject="Override request: contract with blocked counterparty",
        body=(
            f"User {actor.email} has requested an override to create a contract "
            f"'{body.contract_title}' with a non-active counterparty.\n\n"
            f"Reason: {body.reason}\n\n"
            f"Please review this request in the CCRS system."
        ),
        related_resource_type="override_request",
        related_resource_id=str(request_row["id"]),
    )

    await audit_log(
        supabase,
        action="override_request_created",
        resource_type="override_request",
        resource_id=str(request_row["id"]),
        details={"counterparty_id": str(counterparty_id), "reason": body.reason},
        actor=actor,
    )

    return request_row


def list_pending(supabase: Client, limit: int = 25, offset: int = 0):
    query = (
        supabase.table("override_requests")
        .select("*, counterparties(legal_name, status)", count="exact")
        .eq("status", "pending")
        .order("created_at", desc=True)
        .range(offset, offset + limit - 1)
    )
    result = query.execute()
    return result.data, result.count or 0


async def decide(
    supabase: Client,
    request_id: UUID,
    body,
    actor: CurrentUser,
) -> dict | None:
    existing = (
        supabase.table("override_requests")
        .select("*")
        .eq("id", str(request_id))
        .single()
        .execute()
    )
    if not existing.data or existing.data["status"] != "pending":
        return None

    result = (
        supabase.table("override_requests")
        .update(
            {
                "status": body.decision,
                "decided_by": actor.id,
                "decided_by_email": actor.email,
                "decision_comment": body.comment,
                "decided_at": datetime.now(timezone.utc).isoformat(),
            }
        )
        .eq("id", str(request_id))
        .execute()
    )

    row = result.data[0] if result.data else None
    if row:
        await create_notification(
            supabase,
            recipient_email=existing.data["requested_by_email"],
            channel="email",
            subject=f"Override request {body.decision}",
            body=(
                f"Your override request for '{existing.data['contract_title']}' "
                f"has been {body.decision} by {actor.email}."
                + (f"\n\nComment: {body.comment}" if body.comment else "")
            ),
            related_resource_type="override_request",
            related_resource_id=str(request_id),
        )

        await audit_log(
            supabase,
            action=f"override_request_{body.decision}",
            resource_type="override_request",
            resource_id=str(request_id),
            details={"decision": body.decision, "comment": body.comment},
            actor=actor,
        )

    return row
