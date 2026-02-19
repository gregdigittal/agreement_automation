import structlog
from datetime import datetime, timedelta, timezone

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.reminders.schemas import CreateReminderInput, UpdateReminderInput

logger = structlog.get_logger()


def list_reminders(supabase: Client, contract_id: str) -> list[dict]:
    result = (
        supabase.table("reminders")
        .select("*")
        .eq("contract_id", contract_id)
        .order("next_due_at")
        .execute()
    )
    return result.data


async def create_reminder(
    supabase: Client, contract_id: str, body: CreateReminderInput, actor: CurrentUser
) -> dict:
    data = body.model_dump(exclude_unset=True)
    data["contract_id"] = contract_id

    if body.key_date_id:
        kd = (
            supabase.table("contract_key_dates")
            .select("date_value")
            .eq("id", str(body.key_date_id))
            .single()
            .execute()
        )
        if kd.data:
            date_val = kd.data["date_value"]
            if isinstance(date_val, str):
                date_val = datetime.fromisoformat(date_val).date()
            next_due = datetime.combine(
                date_val - timedelta(days=body.lead_days),
                datetime.min.time(),
                tzinfo=timezone.utc,
            )
            data["next_due_at"] = next_due.isoformat()

    if "key_date_id" in data:
        data["key_date_id"] = str(data["key_date_id"])

    result = supabase.table("reminders").insert(data).execute()
    await audit_log(
        supabase,
        action="reminder_created",
        resource_type="reminder",
        resource_id=result.data[0]["id"],
        details={"contract_id": contract_id},
        actor=actor,
    )
    return result.data[0]


async def update_reminder(
    supabase: Client, reminder_id: str, body: UpdateReminderInput, actor: CurrentUser
) -> dict | None:
    data = body.model_dump(exclude_unset=True)
    if not data:
        return None
    result = supabase.table("reminders").update(data).eq("id", reminder_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="reminder_updated",
            resource_type="reminder",
            resource_id=reminder_id,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def delete_reminder(supabase: Client, reminder_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("reminders").delete().eq("id", reminder_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="reminder_deleted",
            resource_type="reminder",
            resource_id=reminder_id,
            actor=actor,
        )
    return bool(result.data)


async def process_due_reminders(supabase: Client) -> int:
    now = datetime.now(timezone.utc)
    now_iso = now.isoformat()

    # Fetch reminders due now that haven't been sent for this cycle:
    # - never sent (last_sent_at IS NULL), OR
    # - last_sent_at < next_due_at (next_due_at was recalculated since last send)
    result = (
        supabase.table("reminders")
        .select("*, contracts(title, id)")
        .eq("is_active", True)
        .lte("next_due_at", now_iso)
        .or_("last_sent_at.is.null,last_sent_at.lt.next_due_at")
        .execute()
    )

    sent_count = 0
    for reminder in result.data or []:
        try:
            supabase.table("notifications").insert(
                {
                    "recipient_email": reminder.get("recipient_email"),
                    "recipient_user_id": reminder.get("recipient_user_id"),
                    "channel": reminder["channel"],
                    "subject": f"CCRS Reminder: {reminder['reminder_type']} for contract",
                    "body": (
                        f"Reminder for contract {reminder.get('contracts', {}).get('title', reminder['contract_id'])}. "
                        f"Type: {reminder['reminder_type']}. Lead time: {reminder['lead_days']} days."
                    ),
                    "related_resource_type": "contract",
                    "related_resource_id": reminder["contract_id"],
                    "status": "pending",
                }
            ).execute()

            # Advance next_due_at by lead_days for recurring behavior
            lead_days = reminder.get("lead_days", 30)
            next_due = now + timedelta(days=lead_days)
            supabase.table("reminders").update(
                {"last_sent_at": now_iso, "next_due_at": next_due.isoformat()}
            ).eq("id", reminder["id"]).execute()
            sent_count += 1
        except Exception as e:
            logger.error("reminder_processing_failed", reminder_id=reminder["id"], error=str(e))

    logger.info("reminders_processed", sent=sent_count)
    return sent_count
