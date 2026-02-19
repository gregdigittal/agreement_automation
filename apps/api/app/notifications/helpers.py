"""Notification creation helpers — called by other modules to enqueue notifications."""

from uuid import UUID

import structlog
from supabase import Client

logger = structlog.get_logger()


async def create_notification(
    supabase: Client,
    *,
    recipient_email: str,
    recipient_user_id: str | None = None,
    channel: str = "email",
    subject: str,
    body: str,
    related_resource_type: str | None = None,
    related_resource_id: str | None = None,
) -> dict:
    """Insert a pending notification row. The scheduler picks it up and sends it."""
    row = {
        "recipient_email": recipient_email,
        "recipient_user_id": recipient_user_id,
        "channel": channel,
        "subject": subject,
        "body": body,
        "related_resource_type": related_resource_type,
        "related_resource_id": related_resource_id,
        "status": "pending",
    }
    result = supabase.table("notifications").insert(row).execute()
    return result.data[0] if result.data else row


async def notify_counterparty_status_change(
    supabase: Client,
    counterparty_id: UUID,
    counterparty_name: str,
    old_status: str,
    new_status: str,
    reason: str,
    changed_by: str,
) -> int:
    """Find all users with active contracts for this counterparty and notify them."""
    contracts = (
        supabase.table("contracts")
        .select("id, title, created_by")
        .eq("counterparty_id", str(counterparty_id))
        .not_.is_("workflow_state", "null")
        .execute()
    )

    if not contracts.data:
        return 0

    emails: set[str] = set()
    for contract in contracts.data:
        if contract.get("created_by"):
            emails.add(contract["created_by"])

    contract_ids = [str(c["id"]) for c in contracts.data]
    instances = (
        supabase.table("workflow_instances")
        .select("id")
        .in_("contract_id", contract_ids)
        .eq("state", "active")
        .execute()
    )
    if instances.data:
        instance_ids = [str(i["id"]) for i in instances.data]
        actions = (
            supabase.table("workflow_stage_actions")
            .select("actor_email")
            .in_("instance_id", instance_ids)
            .execute()
        )
        for action in actions.data or []:
            if action.get("actor_email"):
                emails.add(action["actor_email"])

    subject = f"Counterparty status changed: {counterparty_name}"
    body = (
        f"The counterparty '{counterparty_name}' has been changed from "
        f"'{old_status}' to '{new_status}'.\n\n"
        f"Reason: {reason}\n"
        f"Changed by: {changed_by}\n\n"
        f"This may affect {len(contracts.data)} active contract(s) you are involved with."
    )

    count = 0
    for email in emails:
        await create_notification(
            supabase,
            recipient_email=email,
            channel="email",
            subject=subject,
            body=body,
            related_resource_type="counterparty",
            related_resource_id=str(counterparty_id),
        )
        count += 1

    logger.info(
        "counterparty_status_notifications_sent",
        counterparty_id=str(counterparty_id),
        recipient_count=count,
    )
    return count
